<?php

namespace Ralkage\AdManagement\Command;

use Carbon\Carbon;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Ralkage\AdManagement\Model\Ad;

class SendAdNotificationsCommand extends AbstractCommand
{
    protected $mailer;
    protected $settings;

    public function __construct(Mailer $mailer, SettingsRepositoryInterface $settings)
    {
        parent::__construct();
        $this->mailer = $mailer;
        $this->settings = $settings;
    }

    protected function configure()
    {
        $this
            ->setName('ad-management:send-notifications')
            ->setDescription('Send ad expiration reminders and performance reports to ad owners.');
    }

    protected function fire()
    {
        $sent = 0;

        $sent += $this->sendExpirationReminders();

        if ($this->settings->get('ralkage-ad-management.send_performance_reports', false)) {
            $sent += $this->sendPerformanceReports();
        }

        $this->info("Done. Sent {$sent} email(s).");
    }

    protected function sendExpirationReminders(): int
    {
        $days = (int) $this->settings->get('ralkage-ad-management.expiration_reminder_days', 7);

        if ($days <= 0) {
            return 0;
        }

        $now = Carbon::now();
        $threshold = $now->copy()->addDays($days);

        $expiringAds = Ad::whereNotNull('end_date')
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->where('end_date', '>', $now)
            ->where('end_date', '<=', $threshold)
            // Only notify once per reminder window to prevent duplicate emails on every cron run
            ->where(function ($q) use ($now, $days) {
                $q->whereNull('last_notified_at')
                  ->orWhere('last_notified_at', '<', $now->copy()->subDays($days));
            })
            ->with('owner')
            ->get();

        $sent = 0;
        $forumTitle = $this->settings->get('forum_title', 'Forum');
        $forumUrl = $this->settings->get('url', '');

        foreach ($expiringAds as $ad) {
            $owner = $ad->owner;

            if (!$owner || !$owner->email) {
                continue;
            }

            $daysLeft = (int) $now->diffInDays($ad->end_date, false);
            $daysText = $daysLeft === 1 ? '1 day' : $daysLeft . ' days';

            $vars = [
                'forum_title'     => $forumTitle,
                'forum_url'       => $forumUrl,
                'owner_name'      => $owner->display_name,
                'owner_username'  => $owner->username,
                'ad_name'         => $ad->name,
                'days_left'       => $daysText,
                'expiry_date'     => $ad->end_date->format('F j, Y'),
                'impressions'     => $ad->impressions_count,
                'clicks'          => $ad->clicks_count,
            ];

            $subject = $this->processTemplate(
                $this->getTemplate('expiration_subject', "[{forum_title}] Your ad \"{ad_name}\" expires in {days_left}"),
                $vars
            );
            $body = $this->processTemplate(
                $this->getTemplate('expiration_body', $this->defaultExpirationBody()),
                $vars
            );

            try {
                $this->mailer->raw($body, function (Message $message) use ($owner, $subject) {
                    $message->to($owner->email, $owner->display_name);
                    $message->subject($subject);
                });
                $ad->last_notified_at = $now;
                $ad->save();
                $sent++;
                $this->line("  Sent expiration reminder to {$owner->email} for \"{$ad->name}\"");
            } catch (\Exception $e) {
                $this->error("  Failed to send to {$owner->email}: " . $e->getMessage());
            }
        }

        return $sent;
    }

    protected function sendPerformanceReports(): int
    {
        $ads = Ad::whereNotNull('user_id')
            ->where('is_active', true)
            ->with('owner')
            ->get()
            ->groupBy('user_id');

        $sent = 0;
        $forumTitle = $this->settings->get('forum_title', 'Forum');
        $forumUrl = $this->settings->get('url', '');

        foreach ($ads as $userId => $userAds) {
            $owner = $userAds->first()->owner;

            if (!$owner || !$owner->email) {
                continue;
            }

            $totalImpressions = $userAds->sum('impressions_count');
            $totalClicks = $userAds->sum('clicks_count');
            $ctr = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0;

            $adLines = '';
            foreach ($userAds as $ad) {
                $adCtr = $ad->impressions_count > 0
                    ? round(($ad->clicks_count / $ad->impressions_count) * 100, 2)
                    : 0;
                $adLines .= "  - {$ad->name}: {$ad->impressions_count} impressions, {$ad->clicks_count} clicks ({$adCtr}% CTR)\n";
            }

            $vars = [
                'forum_title'       => $forumTitle,
                'forum_url'         => $forumUrl,
                'owner_name'        => $owner->display_name,
                'owner_username'    => $owner->username,
                'ad_count'          => $userAds->count(),
                'total_impressions' => $totalImpressions,
                'total_clicks'      => $totalClicks,
                'ctr'               => $ctr,
                'ad_lines'          => $adLines,
            ];

            $subject = $this->processTemplate(
                $this->getTemplate('performance_subject', "[{forum_title}] Your ad performance report"),
                $vars
            );
            $body = $this->processTemplate(
                $this->getTemplate('performance_body', $this->defaultPerformanceBody()),
                $vars
            );

            try {
                $this->mailer->raw($body, function (Message $message) use ($owner, $subject) {
                    $message->to($owner->email, $owner->display_name);
                    $message->subject($subject);
                });
                $sent++;
                $this->line("  Sent performance report to {$owner->email}");
            } catch (\Exception $e) {
                $this->error("  Failed to send to {$owner->email}: " . $e->getMessage());
            }
        }

        return $sent;
    }

    /**
     * Replace {placeholder} tokens in a template string with values.
     */
    protected function processTemplate(string $template, array $vars): string
    {
        $search = array_map(fn($k) => '{' . $k . '}', array_keys($vars));
        $replace = array_values($vars);
        return str_replace($search, $replace, $template);
    }

    /**
     * Get a template from settings, falling back to the provided default.
     */
    protected function getTemplate(string $key, string $default): string
    {
        $value = $this->settings->get('ralkage-ad-management.' . $key . '_template', '');
        return $value !== '' ? $value : $default;
    }

    private function defaultExpirationBody(): string
    {
        return <<<'EOT'
Hello {owner_name},

Your advertisement "{ad_name}" on {forum_title} is expiring soon.

Expiration date: {expiry_date}
Time remaining: {days_left}

Current performance:
- Impressions: {impressions}
- Clicks: {clicks}

To manage your ads, visit: {forum_url}/u/{owner_username}/ads

Thank you,
{forum_title}
EOT;
    }

    private function defaultPerformanceBody(): string
    {
        return <<<'EOT'
Hello {owner_name},

Here is your ad performance report for {forum_title}.

Summary:
- Total ads: {ad_count}
- Total impressions: {total_impressions}
- Total clicks: {total_clicks}
- Overall CTR: {ctr}%

Individual ad performance:
{ad_lines}
To manage your ads, visit: {forum_url}/u/{owner_username}/ads

Thank you,
{forum_title}
EOT;
    }
}
