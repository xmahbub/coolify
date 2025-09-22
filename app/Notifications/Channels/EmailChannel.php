<?php

namespace App\Notifications\Channels;

use App\Exceptions\NonReportableException;
use App\Models\Team;
use Exception;
use Illuminate\Notifications\Notification;
use Resend;

class EmailChannel
{
    public function __construct() {}

    public function send(SendsEmail $notifiable, Notification $notification): void
    {
        try {
            // Get team and validate membership before proceeding
            $team = data_get($notifiable, 'id');
            $members = Team::find($team)->members;

            $useInstanceEmailSettings = $notifiable->emailNotificationSettings->use_instance_email_settings;
            $isTransactionalEmail = data_get($notification, 'isTransactionalEmail', false);
            $customEmails = data_get($notification, 'emails', null);

            if ($useInstanceEmailSettings || $isTransactionalEmail) {
                $settings = instanceSettings();
            } else {
                $settings = $notifiable->emailNotificationSettings;
            }

            $isResendEnabled = $settings->resend_enabled;
            $isSmtpEnabled = $settings->smtp_enabled;

            if ($customEmails) {
                $recipients = [$customEmails];
            } else {
                $recipients = $notifiable->getRecipients();
            }

            // Validate team membership for all recipients
            if (count($recipients) === 0) {
                throw new Exception('No email recipients found');
            }

            foreach ($recipients as $recipient) {
                // Check if the recipient is part of the team
                if (! $members->contains('email', $recipient)) {
                    $emailSettings = $notifiable->emailNotificationSettings;
                    data_set($emailSettings, 'smtp_password', '********');
                    data_set($emailSettings, 'resend_api_key', '********');
                    send_internal_notification(sprintf(
                        "Recipient is not part of the team: %s\nTeam: %s\nNotification: %s\nNotifiable: %s\nEmail Settings:\n%s",
                        $recipient,
                        $team,
                        get_class($notification),
                        get_class($notifiable),
                        json_encode($emailSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    ));
                    throw new Exception('Recipient is not part of the team');
                }
            }

            $mailMessage = $notification->toMail($notifiable);

            if ($isResendEnabled) {
                $resend = Resend::client($settings->resend_api_key);
                $from = "{$settings->smtp_from_name} <{$settings->smtp_from_address}>";
                $resend->emails->send([
                    'from' => $from,
                    'to' => $recipients,
                    'subject' => $mailMessage->subject,
                    'html' => (string) $mailMessage->render(),
                ]);
            } elseif ($isSmtpEnabled) {
                $encryption = match (strtolower($settings->smtp_encryption)) {
                    'starttls' => null,
                    'tls' => 'tls',
                    'none' => null,
                    default => null,
                };

                $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                    $settings->smtp_host,
                    $settings->smtp_port,
                    $encryption
                );
                $transport->setUsername($settings->smtp_username ?? '');
                $transport->setPassword($settings->smtp_password ?? '');

                $mailer = new \Symfony\Component\Mailer\Mailer($transport);

                $fromEmail = $settings->smtp_from_address ?? 'noreply@localhost';
                $fromName = $settings->smtp_from_name ?? 'System';
                $from = new \Symfony\Component\Mime\Address($fromEmail, $fromName);
                $email = (new \Symfony\Component\Mime\Email)
                    ->from($from)
                    ->to(...$recipients)
                    ->subject($mailMessage->subject)
                    ->html((string) $mailMessage->render());

                $mailer->send($email);
            }
        } catch (\Throwable $e) {
            // Check if this is a Resend domain verification error on cloud instances
            if (isCloud() && str_contains($e->getMessage(), 'domain is not verified')) {
                // Throw as NonReportableException so it won't go to Sentry
                throw NonReportableException::fromException($e);
            }
            throw $e;
        }
    }
}
