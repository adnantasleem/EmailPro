<?php

namespace App\Services;

use App\Models\Recipient;
use App\Models\Contact;
use App\Models\DisposableDomain;

class EmailValidatorService
{
    /**
     * List of role-based email prefixes that often have delivery issues.
     */
    protected array $roleBasedPrefixes = [
        'info', 'admin', 'administrator', 'support', 'help', 'contact',
        'sales', 'marketing', 'billing', 'accounts', 'noreply', 'no-reply',
        'no_reply', 'donotreply', 'do-not-reply', 'webmaster', 'postmaster',
        'hostmaster', 'abuse', 'spam', 'security', 'privacy', 'legal',
        'hr', 'jobs', 'careers', 'recruitment', 'office', 'hello',
        'team', 'enquiries', 'enquiry', 'feedback', 'mail', 'email',
    ];

    /**
     * Common domain typos and their corrections.
     */
    protected array $domainTypos = [
        // Gmail typos
        'gmial.com' => 'gmail.com',
        'gmal.com' => 'gmail.com',
        'gmaill.com' => 'gmail.com',
        'gamil.com' => 'gmail.com',
        'gnail.com' => 'gmail.com',
        'gmail.co' => 'gmail.com',
        'gmail.cm' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'gmil.com' => 'gmail.com',
        'gemail.com' => 'gmail.com',
        'gimail.com' => 'gmail.com',
        
        // Hotmail typos
        'hotmal.com' => 'hotmail.com',
        'hotmai.com' => 'hotmail.com',
        'hotmial.com' => 'hotmail.com',
        'hotamil.com' => 'hotmail.com',
        'hotmail.co' => 'hotmail.com',
        'hotmail.cm' => 'hotmail.com',
        'hotmaill.com' => 'hotmail.com',
        'homail.com' => 'hotmail.com',
        'htmail.com' => 'hotmail.com',
        
        // Yahoo typos
        'yaho.com' => 'yahoo.com',
        'yahooo.com' => 'yahoo.com',
        'yhoo.com' => 'yahoo.com',
        'yaoo.com' => 'yahoo.com',
        'yahoo.co' => 'yahoo.com',
        'yahoo.cm' => 'yahoo.com',
        'yhaoo.com' => 'yahoo.com',
        
        // Outlook typos
        'outlok.com' => 'outlook.com',
        'outloo.com' => 'outlook.com',
        'outllok.com' => 'outlook.com',
        'outlookk.com' => 'outlook.com',
        'outlook.co' => 'outlook.com',
        'outlook.cm' => 'outlook.com',
        'outloook.com' => 'outlook.com',
        
        // Live typos
        'liv.com' => 'live.com',
        'livee.com' => 'live.com',
        
        // iCloud typos
        'iclod.com' => 'icloud.com',
        'icloud.co' => 'icloud.com',
        'icoud.com' => 'icloud.com',
        
        // AOL typos
        'aoll.com' => 'aol.com',
        'aol.co' => 'aol.com',
    ];

    /**
     * Validate an email address completely.
     */
    public function validate(Recipient $recipient): array
    {
        $email = strtolower(trim($recipient->email));
        $result = [
            'email' => $email,
            'syntax_valid' => false,
            'domain_exists' => false,
            'mx_exists' => false,
            'is_disposable' => false,
            'is_role_based' => false,
            'has_typo' => false,
            'suggested_correction' => null,
            'is_valid' => false,
            'reason' => null,
        ];

        // Step 1: Validate syntax
        if (!$this->validateSyntax($email)) {
            $result['reason'] = 'Invalid email syntax';
            $recipient->markAsInvalid($result, $result['reason']);
            return $result;
        }
        $result['syntax_valid'] = true;

        // Step 2: Extract domain
        $domain = $this->extractDomain($email);
        $localPart = $this->extractLocalPart($email);

        // Step 3: Check for common typos FIRST (before DNS checks)
        $typoResult = $this->checkForTypo($domain);
        if ($typoResult !== false) {
            $result['has_typo'] = true;
            $result['suggested_correction'] = $localPart . '@' . $typoResult;
            $result['reason'] = "Domain typo detected. Did you mean: {$typoResult}?";
            $recipient->markAsInvalid($result, $result['reason']);
            return $result;
        }

        // Step 4: Check if domain exists
        if (!$this->checkDomainExists($domain)) {
            $result['reason'] = 'Domain does not exist';
            $recipient->markAsInvalid($result, $result['reason']);
            return $result;
        }
        $result['domain_exists'] = true;

        // Step 5: Check MX records
        if (!$this->checkMxRecords($domain)) {
            $result['reason'] = 'No MX records found';
            $recipient->markAsInvalid($result, $result['reason']);
            return $result;
        }
        $result['mx_exists'] = true;

        // Step 6: Check if disposable email
        if ($this->isDisposable($email)) {
            $result['is_disposable'] = true;
            $result['reason'] = 'Disposable email domain';
            $recipient->markAsDisposable($result);
            return $result;
        }

        // All checks passed
        $result['is_valid'] = true;
        $recipient->markAsValid($result);

        return $result;
    }

    /**
     * Validate a Contact model (for contact list import validation).
     */
    public function validateContact(Contact $contact): array
    {
        $result = $this->validateEmail($contact->email);

        if ($result['is_valid']) {
            $contact->markAsValid($result);
        } else {
            $contact->markAsInvalid($result, $result['reason']);
        }

        return $result;
    }

    /**
     * Validate an email address without updating any model.
     * Returns validation result array.
     */
    public function validateEmail(string $email): array
    {
        $email = strtolower(trim($email));
        $result = [
            'email' => $email,
            'syntax_valid' => false,
            'domain_exists' => false,
            'mx_exists' => false,
            'mailbox_exists' => null, // null = not checked, true = exists, false = doesn't exist
            'is_disposable' => false,
            'is_role_based' => false,
            'has_typo' => false,
            'suggested_correction' => null,
            'is_valid' => false,
            'reason' => null,
        ];

        // Step 1: Validate syntax
        if (!$this->validateSyntax($email)) {
            $result['reason'] = 'Invalid email syntax';
            return $result;
        }
        $result['syntax_valid'] = true;

        // Step 2: Extract domain
        $domain = $this->extractDomain($email);
        $localPart = $this->extractLocalPart($email);

        // Step 3: Check for common typos FIRST (before DNS checks)
        $typoResult = $this->checkForTypo($domain);
        if ($typoResult !== false) {
            $result['has_typo'] = true;
            $result['suggested_correction'] = $localPart . '@' . $typoResult;
            $result['reason'] = "Domain typo detected. Did you mean: {$typoResult}?";
            return $result;
        }

        // Step 4: Check if domain exists
        if (!$this->checkDomainExists($domain)) {
            $result['reason'] = 'Domain does not exist';
            return $result;
        }
        $result['domain_exists'] = true;

        // Step 5: Check MX records
        if (!$this->checkMxRecords($domain)) {
            $result['reason'] = 'No MX records found';
            return $result;
        }
        $result['mx_exists'] = true;

        // Step 6: Check if disposable email
        if ($this->isDisposable($email)) {
            $result['is_disposable'] = true;
            $result['reason'] = 'Disposable email domain';
            return $result;
        }

        // Step 7: SMTP mailbox verification (check if mailbox exists)
        $mailboxCheck = $this->checkMailboxExists($email, $domain);
        $result['mailbox_exists'] = $mailboxCheck['exists'];
        
        if ($mailboxCheck['exists'] === false) {
            $result['reason'] = $mailboxCheck['reason'] ?? 'Mailbox does not exist';
            return $result;
        }

        // All checks passed
        $result['is_valid'] = true;
        return $result;
    }

    /**
     * Validate email syntax using filter_var.
     */
    protected function validateSyntax(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Extract domain from email.
     */
    protected function extractDomain(string $email): string
    {
        return strtolower(substr(strrchr($email, '@'), 1));
    }

    /**
     * Extract local part (before @) from email.
     */
    protected function extractLocalPart(string $email): string
    {
        return strtolower(substr($email, 0, strpos($email, '@')));
    }

    /**
     * Check if email is role-based.
     */
    protected function isRoleBased(string $localPart): bool
    {
        // Remove numbers and special chars for matching
        $cleanLocal = preg_replace('/[0-9_\-\.]+$/', '', $localPart);
        
        return in_array($cleanLocal, $this->roleBasedPrefixes, true);
    }

    /**
     * Check for common domain typos.
     * Returns the corrected domain or false if no typo detected.
     */
    protected function checkForTypo(string $domain): string|false
    {
        if (isset($this->domainTypos[$domain])) {
            return $this->domainTypos[$domain];
        }
        
        return false;
    }

    /**
     * Check if domain exists using DNS lookup.
     */
    protected function checkDomainExists(string $domain): bool
    {
        // First check if there are any DNS records
        $records = @dns_get_record($domain, DNS_A | DNS_AAAA);
        
        if ($records && count($records) > 0) {
            return true;
        }

        // Also check for MX as some domains only have MX records
        $mxRecords = @dns_get_record($domain, DNS_MX);
        
        return $mxRecords && count($mxRecords) > 0;
    }

    /**
     * Check if domain has MX records.
     */
    protected function checkMxRecords(string $domain): bool
    {
        $mxhosts = [];
        $weights = [];
        
        if (@getmxrr($domain, $mxhosts, $weights)) {
            return count($mxhosts) > 0;
        }

        // Some domains use A record as fallback
        $records = @dns_get_record($domain, DNS_A);
        return $records && count($records) > 0;
    }

    /**
     * Check if email domain is disposable.
     */
    protected function isDisposable(string $email): bool
    {
        return DisposableDomain::isDisposable($email);
    }

    /**
     * Validate a batch of recipients.
     */
    public function validateBatch(array $recipients): array
    {
        $results = [];
        
        foreach ($recipients as $recipient) {
            $results[] = $this->validate($recipient);
        }

        return $results;
    }

    /**
     * Get list of role-based prefixes.
     */
    public function getRoleBasedPrefixes(): array
    {
        return $this->roleBasedPrefixes;
    }

    /**
     * Get list of known typos.
     */
    public function getKnownTypos(): array
    {
        return $this->domainTypos;
    }

    /**
     * Domains that block SMTP verification - skip mailbox check for these.
     */
    protected array $skipSmtpVerification = [
        'gmail.com', 'googlemail.com',
        'yahoo.com', 'yahoo.co.uk', 'yahoo.fr', 'yahoo.de', 'yahoo.it', 'yahoo.es',
        'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
        'icloud.com', 'me.com', 'mac.com',
        'aol.com',
        'protonmail.com', 'proton.me',
        'zoho.com',
    ];

    /**
     * Check if mailbox exists using SMTP RCPT TO verification.
     * Returns ['exists' => true/false/null, 'reason' => string]
     * null means verification was skipped or inconclusive.
     */
    protected function checkMailboxExists(string $email, string $domain): array
    {
        // Skip verification for major providers that block it
        if (in_array(strtolower($domain), $this->skipSmtpVerification)) {
            return ['exists' => null, 'reason' => 'Skipped - major provider'];
        }

        // Get MX records
        $mxhosts = [];
        $weights = [];
        if (!@getmxrr($domain, $mxhosts, $weights)) {
            return ['exists' => null, 'reason' => 'Could not get MX records'];
        }

        // Check if domain uses Google Workspace (Google MX servers)
        // Google MX records contain: aspmx.l.google.com, googlemail.com, etc.
        foreach ($mxhosts as $mxhost) {
            $mxLower = strtolower($mxhost);
            if (str_contains($mxLower, 'google.com') || str_contains($mxLower, 'googlemail.com')) {
                return ['exists' => null, 'reason' => 'Skipped - Google Workspace domain'];
            }
            // Also skip Microsoft 365 domains
            if (str_contains($mxLower, 'outlook.com') || str_contains($mxLower, 'protection.outlook.com')) {
                return ['exists' => null, 'reason' => 'Skipped - Microsoft 365 domain'];
            }
        }

        // Sort by priority (lower weight = higher priority)
        array_multisort($weights, SORT_ASC, $mxhosts);

        // Try each MX host
        foreach ($mxhosts as $mxhost) {
            $result = $this->smtpVerify($email, $mxhost);
            if ($result['checked']) {
                return $result;
            }
        }

        return ['exists' => null, 'reason' => 'Could not connect to mail server'];
    }

    /**
     * Perform SMTP verification against a specific mail server.
     * Enhanced with catch-all detection and greylisting retry.
     */
    protected function smtpVerify(string $email, string $mxhost): array
    {
        $timeout = 15; // seconds
        $port = 25;
        $maxRetries = 2;
        $retryDelay = 3; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $result = $this->attemptSmtpVerification($email, $mxhost, $port, $timeout);
            
            // If we get a definitive answer, return it
            if ($result['checked'] && $result['exists'] !== null) {
                return $result;
            }
            
            // If greylisting detected, wait and retry
            if (isset($result['greylisting']) && $result['greylisting'] && $attempt < $maxRetries) {
                sleep($retryDelay);
                continue;
            }
            
            // If connection failed, try port 587 as backup
            if (!$result['checked'] && $port === 25 && $attempt === 1) {
                $port = 587;
                continue;
            }
            
            return $result;
        }
        
        return ['exists' => null, 'checked' => false, 'reason' => 'Verification failed after retries'];
    }

    /**
     * Single SMTP verification attempt.
     */
    protected function attemptSmtpVerification(string $email, string $mxhost, int $port, int $timeout): array
    {
        // Try to connect
        $socket = @fsockopen($mxhost, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return ['exists' => null, 'checked' => false, 'reason' => "Connection failed: {$errstr}"];
        }

        stream_set_timeout($socket, $timeout);

        try {
            // Read greeting
            $response = $this->smtpRead($socket);
            if (!$this->smtpResponseOk($response, 220)) {
                throw new \Exception('Invalid greeting');
            }

            // Send EHLO
            $this->smtpWrite($socket, "EHLO verify.local\r\n");
            $response = $this->smtpRead($socket);
            if (!$this->smtpResponseOk($response, 250)) {
                // Try HELO as fallback
                $this->smtpWrite($socket, "HELO verify.local\r\n");
                $response = $this->smtpRead($socket);
                if (!$this->smtpResponseOk($response, 250)) {
                    throw new \Exception('HELO/EHLO failed');
                }
            }

            // Send MAIL FROM
            $this->smtpWrite($socket, "MAIL FROM:<verify@verify.local>\r\n");
            $response = $this->smtpRead($socket);
            if (!$this->smtpResponseOk($response, 250)) {
                throw new \Exception('MAIL FROM rejected');
            }

            // First: Check if domain is catch-all by sending a random email
            $randomEmail = 'test_invalid_' . bin2hex(random_bytes(8)) . '@' . substr(strrchr($email, '@'), 1);
            $this->smtpWrite($socket, "RCPT TO:<{$randomEmail}>\r\n");
            $catchAllResponse = $this->smtpRead($socket);
            $catchAllCode = (int) substr($catchAllResponse, 0, 3);
            
            $isCatchAll = ($catchAllCode === 250 || $catchAllCode === 251);
            
            // Reset connection for actual email check
            $this->smtpWrite($socket, "RSET\r\n");
            $this->smtpRead($socket);
            
            // Send MAIL FROM again after reset
            $this->smtpWrite($socket, "MAIL FROM:<verify@verify.local>\r\n");
            $this->smtpRead($socket);

            // Now check the actual email
            $this->smtpWrite($socket, "RCPT TO:<{$email}>\r\n");
            $response = $this->smtpRead($socket);
            $code = (int) substr($response, 0, 3);

            // Send QUIT
            $this->smtpWrite($socket, "QUIT\r\n");
            fclose($socket);

            // If domain is catch-all, we can't reliably verify
            if ($isCatchAll) {
                return [
                    'exists' => null, 
                    'checked' => true, 
                    'reason' => 'Catch-all domain - cannot verify individual mailboxes',
                    'is_catch_all' => true
                ];
            }

            // Interpret response
            if ($code === 250 || $code === 251) {
                return ['exists' => true, 'checked' => true, 'reason' => 'Mailbox verified'];
            } elseif ($code === 550 || $code === 551 || $code === 552 || $code === 553 || $code === 554) {
                // Check response text for more specific errors
                $responseText = strtolower($response);
                if (str_contains($responseText, 'does not exist') || 
                    str_contains($responseText, 'user unknown') ||
                    str_contains($responseText, 'no such user') ||
                    str_contains($responseText, 'invalid recipient') ||
                    str_contains($responseText, 'recipient rejected')) {
                    return ['exists' => false, 'checked' => true, 'reason' => 'Mailbox does not exist'];
                }
                // Some 550 errors are policy-based, not mailbox non-existence
                return ['exists' => null, 'checked' => true, 'reason' => "Rejected: {$response}"];
            } elseif ($code === 450 || $code === 451 || $code === 452) {
                // Temporary failure (possibly greylisting)
                return [
                    'exists' => null, 
                    'checked' => true, 
                    'reason' => 'Temporary failure (greylisting)',
                    'greylisting' => true
                ];
            } elseif ($code === 421) {
                // Service not available, connection closing
                return ['exists' => null, 'checked' => false, 'reason' => 'Service temporarily unavailable'];
            } else {
                // Unknown response - treat as inconclusive
                return ['exists' => null, 'checked' => true, 'reason' => "Unknown response: {$code}"];
            }

        } catch (\Exception $e) {
            @fclose($socket);
            return ['exists' => null, 'checked' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Read response from SMTP socket.
     */
    protected function smtpRead($socket): string
    {
        $response = '';
        while ($line = @fgets($socket, 512)) {
            $response .= $line;
            // Check if this is the last line (format: "XXX message" not "XXX-message")
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    /**
     * Write command to SMTP socket.
     */
    protected function smtpWrite($socket, string $command): void
    {
        @fwrite($socket, $command);
    }

    /**
     * Check if SMTP response code matches expected.
     */
    protected function smtpResponseOk(string $response, int $expectedCode): bool
    {
        return (int) substr($response, 0, 3) === $expectedCode;
    }
}
