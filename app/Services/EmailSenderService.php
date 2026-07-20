<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Recipient;
use App\Models\SmtpConfig;
use App\Models\SubjectLine;
use App\Models\BodyTemplate;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;
use Swift_SmtpTransport;
use Swift_Mailer;
use Exception;

class EmailSenderService
{
    protected SmtpSelectorService $smtpSelector;
    protected ContentRotatorService $contentRotator;
    public function __construct(
        SmtpSelectorService $smtpSelector,
        ContentRotatorService $contentRotator
    ) {
        $this->smtpSelector = $smtpSelector;
        $this->contentRotator = $contentRotator;
    }

    /**
     * Send an email to a recipient with full rotation and logging.
     */
    public function sendEmail(Campaign $campaign, Recipient $recipient): array
    {
        $result = [
            'success' => false,
            'smtp_id' => null,
            'subject_id' => null,
            'body_id' => null,
            'error' => null,
            'delay_applied' => 0,
        ];

        try {
            // Check user's email limits and account expiration
            $user = $campaign->user;
            if ($user && !$user->canSendEmails(1)) {
                if ($user->isAccountExpired()) {
                    throw new Exception('Account has expired.');
                } elseif ($user->hasReachedDailyEmailLimit()) {
                    throw new Exception('Daily email limit reached (' . $user->daily_email_limit . ' emails). Limit resets tomorrow.');
                } elseif ($user->hasReachedEmailLimit()) {
                    throw new Exception('Monthly email limit reached (' . $user->monthly_email_limit . ' emails). Limit resets on ' . now()->addMonth()->startOfMonth()->format('M 1, Y') . '.');
                } elseif ($user->hasReachedYearlyEmailLimit()) {
                    throw new Exception('Yearly email limit reached (' . $user->yearly_email_limit . ' emails).');
                } else {
                    throw new Exception('Sending limit reached.');
                }
            }

            // Select random SMTP allowed for this campaign
            $smtp = $this->smtpSelector->selectSmtpForCampaign($campaign);
            if (!$smtp) {
                throw new Exception('No available SMTP accounts');
            }
            $result['smtp_id'] = $smtp->id;

            // Select random subject
            $subject = $this->contentRotator->getRandomSubject($campaign);
            if (!$subject) {
                throw new Exception('No subject lines configured');
            }
            $result['subject_id'] = $subject->id;

            // Select random body
            $body = $this->contentRotator->getRandomBody($campaign);
            if (!$body) {
                throw new Exception('No body templates configured');
            }
            $result['body_id'] = $body->id;

            // Delays are now handled implicitly by the SendEmailsJob loop checking SMTP cooldowns
            $result['delay_applied'] = 0;

            // Prepare email content with variable replacement
            $unsubscribeUrl = $recipient->getUnsubscribeUrl();
            $htmlContent = $body->getProcessedHtml($recipient, $unsubscribeUrl);
            $plainContent = $body->getProcessedPlainText($recipient, $unsubscribeUrl);

            // Insert tracking pixel at the end of HTML content
            $trackingPixel = $recipient->getTrackingPixelHtml();
            if (stripos($htmlContent, '</body>') !== false) {
                $htmlContent = str_ireplace('</body>', $trackingPixel . '</body>', $htmlContent);
            } else {
                $htmlContent .= $trackingPixel;
            }

            // Send the email
            $this->dispatchEmail($campaign, $smtp, $recipient, $subject->subject, $htmlContent, $plainContent);

            // Update counters and logs (use recordSend for bounce tracking)
            $smtp->recordSend();
            $this->contentRotator->trackUsage($subject, $body);
            $recipient->markAsSent();

            EmailLog::logSuccess($campaign, $recipient, $smtp, $subject, $body);

            $result['success'] = true;

        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            $result['error'] = $errorMsg;

            // Log failure and record bounce if we have SMTP info
            if (isset($smtp)) {
                $smtp->recordBounce();
                
                if (isset($subject) && isset($body)) {
                    EmailLog::logFailure($campaign, $recipient, $smtp, $subject, $body, $errorMsg);
                }
            }

            // Don't mark recipient as failed if it's a quota/capacity issue
            if ($errorMsg === 'No available SMTP accounts' || str_starts_with($errorMsg, 'Monthly email limit reached')) {
                // Return success=false but keep recipient in pending/valid state for later
            } else {
                $recipient->markAsFailed($errorMsg);
            }
        }

        return $result;
    }

    /**
     * Actually dispatch the email using the specified SMTP.
     */
    protected function dispatchEmail(
        Campaign $campaign,
        SmtpConfig $smtp,
        Recipient $recipient,
        string $subject,
        string $htmlContent,
        string $plainContent
    ): void {
        // Build DSN based on encryption type
        // ssl = smtps:// (port 465)
        // tls = smtp:// with STARTTLS (port 587)
        // null = smtp:// without encryption
        
        $encryption = strtolower($smtp->encryption ?? '');
        
        if ($encryption === 'ssl') {
            // SSL uses smtps:// protocol
            $dsn = sprintf(
                'smtps://%s:%s@%s:%d',
                urlencode($smtp->username),
                urlencode($smtp->decrypted_password),
                $smtp->host,
                $smtp->port
            );
        } else {
            // TLS or no encryption uses smtp://
            $dsn = sprintf(
                'smtp://%s:%s@%s:%d',
                urlencode($smtp->username),
                urlencode($smtp->decrypted_password),
                $smtp->host,
                $smtp->port
            );
        }

        $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        // Determine From Name: Campaign > SMTP default
        $fromName = !empty($campaign->from_name) ? $campaign->from_name : $smtp->from_name;

        // Build email message
        $email = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address($smtp->from_email, $fromName))
            ->to($recipient->email)
            ->subject($subject)
            ->html($htmlContent)
            ->text($plainContent);

        // Add Reply-To header if set in campaign
        if (!empty($campaign->reply_to)) {
            $email->replyTo($campaign->reply_to);
        }

        // Send
        $mailer->send($email);
    }

    // Note: sendBatch was removed in favor of the SMTP-led pacing in SendEmailsJob

    /**
     * Send a test email for a campaign without affecting analytics or quotas.
     */
    public function sendTestEmail(
        Campaign $campaign,
        string $testEmailAddress,
        int $smtpId,
        int $subjectId,
        int $bodyId,
        array $variableData = []
    ): array {
        $result = [
            'success' => false,
            'error' => null,
        ];

        try {
            $smtp = SmtpConfig::findOrFail($smtpId);
            $subject = SubjectLine::findOrFail($subjectId);
            $body = BodyTemplate::findOrFail($bodyId);

            // Create a mock recipient
            $recipient = new Recipient([
                'campaign_id' => $campaign->id,
                'email' => $testEmailAddress,
                'name' => $variableData['name'] ?? null,
                'first_name' => $variableData['first_name'] ?? null,
                'last_name' => $variableData['last_name'] ?? null,
                'status' => Recipient::STATUS_VALID,
            ]);
            // Force an ID for things like unsubscribe URLs
            $recipient->id = 999999999; 

            // Prepare email content with variable replacement
            $unsubscribeUrl = $recipient->getUnsubscribeUrl();
            $htmlContent = $body->getProcessedHtml($recipient, $unsubscribeUrl);
            $plainContent = $body->getProcessedPlainText($recipient, $unsubscribeUrl);

            // Send the email directly (bypasses EmailLog and Quota tracking)
            $this->dispatchEmail($campaign, $smtp, $recipient, $subject->subject, $htmlContent, $plainContent);

            $result['success'] = true;
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }
}
