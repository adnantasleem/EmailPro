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
    protected DelayManagerService $delayManager;

    public function __construct(
        SmtpSelectorService $smtpSelector,
        ContentRotatorService $contentRotator,
        DelayManagerService $delayManager
    ) {
        $this->smtpSelector = $smtpSelector;
        $this->contentRotator = $contentRotator;
        $this->delayManager = $delayManager;
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
            // Check user's monthly email limit
            $user = $campaign->user;
            if ($user && $user->hasReachedEmailLimit()) {
                throw new Exception('Monthly email limit reached (' . $user->monthly_email_limit . ' emails). Limit resets on ' . now()->addMonth()->startOfMonth()->format('M 1, Y') . '.');
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

            // Apply random delay
            $result['delay_applied'] = $this->delayManager->applyDelayWithVariation(
                $campaign->min_delay_seconds,
                $campaign->max_delay_seconds
            );

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

    /**
     * Send a batch of emails for a campaign.
     */
    public function sendBatch(Campaign $campaign, int $batchSize): array
    {
        $results = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'details' => [],
        ];

        // Auto-sync: promote pending recipients whose contacts have been validated in the contact list
        $pendingRecipients = $campaign->recipients()
            ->whereIn('status', [Recipient::STATUS_PENDING, Recipient::STATUS_VALIDATING])
            ->pluck('email')
            ->toArray();

        if (!empty($pendingRecipients)) {
            // Find which of these emails are now valid in the contact list
            $validatedEmails = \App\Models\Contact::whereHas('contactList', function ($q) use ($campaign) {
                    $q->where('user_id', $campaign->user_id);
                })
                ->whereIn('email', $pendingRecipients)
                ->where('validation_status', \App\Models\Contact::STATUS_VALID)
                ->pluck('email')
                ->toArray();

            if (!empty($validatedEmails)) {
                $campaign->recipients()
                    ->whereIn('status', [Recipient::STATUS_PENDING, Recipient::STATUS_VALIDATING])
                    ->whereIn('email', $validatedEmails)
                    ->update(['status' => Recipient::STATUS_VALID]);

                \Illuminate\Support\Facades\Log::info("SendBatch: Promoted " . count($validatedEmails) . " pending recipients to valid for campaign {$campaign->id}");
            }
        }

        // Get recipients ready to send
        $recipients = $campaign->recipients()
            ->readyToSend()
            ->limit($batchSize)
            ->get();

        $results['total'] = $recipients->count();

        foreach ($recipients as $recipient) {
            $sendResult = $this->sendEmail($campaign, $recipient);
            
            if ($sendResult['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = [
                'recipient_id' => $recipient->id,
                'email' => $recipient->email,
                'success' => $sendResult['success'],
                'error' => $sendResult['error'],
            ];

            // Check if we can still send (SMTP might have hit limit)
            if (!$this->smtpSelector->canSendAnyForCampaign($campaign)) {
                // If capacity is exhausted, auto-pause the campaign
                $campaign->update([
                    'status' => Campaign::STATUS_PAUSED,
                    'pause_reason' => Campaign::PAUSE_REASON_QUOTA,
                ]);
                
                \Illuminate\Support\Facades\Log::info("SendBatch: Campaign {$campaign->id} paused because all SMTP quotas are exhausted.");
                break;
            }
        }

        // Check if campaign is completed
        // Only mark as completed if there are NO recipients left that could potentially be sent
        // This includes: valid (ready now), pending (still being validated), validating, and failed (could retry)
        $remainingUnsent = $campaign->recipients()
            ->whereIn('status', [
                Recipient::STATUS_VALID,
                Recipient::STATUS_PENDING,
                Recipient::STATUS_VALIDATING,
                Recipient::STATUS_FAILED,
            ])
            ->count();
        if ($remainingUnsent === 0) {
            $campaign->update([
                'status' => Campaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }

        return $results;
    }

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
