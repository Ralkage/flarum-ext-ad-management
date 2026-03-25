<?php

namespace Ralkage\AdManagement\Service;

use Carbon\Carbon;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Ralkage\AdManagement\Model\Ad;
use Ralkage\AdManagement\Model\AdClick;
use Ralkage\AdManagement\Model\AdZone;

class AdService
{
    protected $settings;
    protected $imageService;

    public function __construct(SettingsRepositoryInterface $settings, ImageService $imageService)
    {
        $this->settings = $settings;
        $this->imageService = $imageService;
    }

    public function getActiveAdsForZone(string $zoneName, ?User $user = null): array
    {
        $zone = AdZone::where('name', $zoneName)->where('is_active', true)->first();

        if (!$zone) {
            return [];
        }

        $ads = $zone->activeAds()->get();

        $ads = $ads->filter(function (Ad $ad) use ($user) {
            return $ad->isVisibleToUser($user);
        });

        return $ads->values()->all();
    }

    public function getAllActiveAds(?User $user = null): array
    {
        $zones = AdZone::where('is_active', true)->with('activeAds')->get();
        $result = [];

        foreach ($zones as $zone) {
            $ads = $zone->activeAds;

            $ads = $ads->filter(function (Ad $ad) use ($user) {
                return $ad->isVisibleToUser($user);
            });

            if ($ads->isNotEmpty()) {
                $result[$zone->name] = $ads->values()->all();
            }
        }

        return $result;
    }

    public function createAd(array $data, ?User $actor = null): Ad
    {
        $isAdmin = $actor && $actor->isAdmin();

        $ad = new Ad();
        $ad->zone_id = Arr::get($data, 'zone_id');
        $ad->name = Arr::get($data, 'name');

        if ($isAdmin) {
            $ad->user_id = Arr::get($data, 'user_id', $actor->id);
            $ad->type = Arr::get($data, 'type', 'image');
            $ad->content = Arr::get($data, 'content');
            $ad->is_active = Arr::get($data, 'is_active', true);
            $ad->status = $ad->is_active ? 'active' : 'inactive';
            $ad->start_date = Arr::get($data, 'start_date');
            $ad->end_date = Arr::get($data, 'end_date');
            $ad->priority = Arr::get($data, 'priority', 0);
            $ad->group_visibility = Arr::get($data, 'group_visibility');
            $ad->max_impressions = Arr::get($data, 'max_impressions');
            $ad->max_clicks = Arr::get($data, 'max_clicks');
            $ad->max_image_changes = Arr::get($data, 'max_image_changes',
                (int) $this->settings->get('ralkage-ad-management.default_max_image_changes', 5));
        } else {
            // Non-admin: image-only, pending review, no admin-only fields
            $ad->user_id = $actor ? $actor->id : null;
            $ad->type = 'image';
            $ad->is_active = false;
            $ad->status = 'pending_review';
            $ad->priority = 0;
            $ad->max_image_changes = (int) $this->settings->get('ralkage-ad-management.default_max_image_changes', 5);
        }

        $imageUrl = Arr::get($data, 'image_url');
        if ($imageUrl && $ad->type === 'image') {
            $this->imageService->validateImageUrl($imageUrl);
            $zone = AdZone::find($ad->zone_id);
            $imageUrl = $this->imageService->processImage(
                $imageUrl,
                $zone ? $zone->max_width : null,
                $zone ? $zone->max_height : null
            );
        }
        $ad->image_url = $imageUrl;
        $linkUrl = Arr::get($data, 'link_url');
        $this->validateLinkUrl($linkUrl);
        $ad->link_url = $linkUrl;
        $ad->alt_text = Arr::get($data, 'alt_text');
        $ad->width = Arr::get($data, 'width');
        $ad->height = Arr::get($data, 'height');
        $ad->save();

        return $ad;
    }

    public function updateAd(Ad $ad, array $data, ?User $actor = null): Ad
    {
        $isAdmin = $actor && $actor->isAdmin();

        if (Arr::has($data, 'zone_id')) {
            $ad->zone_id = Arr::get($data, 'zone_id');
        }
        if (Arr::has($data, 'name')) {
            $ad->name = Arr::get($data, 'name');
        }
        if (Arr::has($data, 'type') && $isAdmin) {
            $ad->type = Arr::get($data, 'type');
        }
        if (Arr::has($data, 'content') && $isAdmin) {
            $ad->content = Arr::get($data, 'content');
        }
        if (Arr::has($data, 'link_url')) {
            $linkUrl = Arr::get($data, 'link_url');
            $this->validateLinkUrl($linkUrl);
            $ad->link_url = $linkUrl;
        }
        if (Arr::has($data, 'alt_text')) {
            $ad->alt_text = Arr::get($data, 'alt_text');
        }
        // Approve/reject pending image change
        if ($isAdmin && Arr::has($data, 'pending_image_action')) {
            $action = Arr::get($data, 'pending_image_action');
            if ($action === 'approve' && $ad->pending_image_url) {
                if ($ad->image_url) {
                    $this->imageService->deleteCompressedImage($ad->image_url);
                }
                $ad->image_url = $ad->pending_image_url;
                $ad->pending_image_url = null;
                // Count was already incremented at submission time
            } elseif ($action === 'reject') {
                if ($ad->pending_image_url) {
                    $this->imageService->deleteCompressedImage($ad->pending_image_url);
                }
                $ad->pending_image_url = null;
            }
        }

        // Explicit status change (approve/reject workflow)
        if (Arr::has($data, 'status') && $isAdmin) {
            $status = Arr::get($data, 'status');
            if (in_array($status, ['active', 'inactive', 'rejected'], true)) {
                $ad->status = $status;
                $ad->is_active = ($status === 'active');
            }
        } elseif (Arr::has($data, 'is_active') && $isAdmin) {
            $newActive = (bool) Arr::get($data, 'is_active');
            $ad->is_active = $newActive;
            if ($newActive) {
                $ad->status = 'active';
            } elseif (!in_array($ad->status, ['pending_review', 'rejected'])) {
                $ad->status = 'inactive';
            }
        }
        if (Arr::has($data, 'start_date') && $isAdmin) {
            $ad->start_date = Arr::get($data, 'start_date');
        }
        if (Arr::has($data, 'end_date') && $isAdmin) {
            $ad->end_date = Arr::get($data, 'end_date');
        }
        if (Arr::has($data, 'priority') && $isAdmin) {
            $ad->priority = Arr::get($data, 'priority');
        }
        if (Arr::has($data, 'group_visibility') && $isAdmin) {
            $ad->group_visibility = Arr::get($data, 'group_visibility');
        }
        if (Arr::has($data, 'max_impressions') && $isAdmin) {
            $ad->max_impressions = Arr::get($data, 'max_impressions');
        }
        if (Arr::has($data, 'max_clicks') && $isAdmin) {
            $ad->max_clicks = Arr::get($data, 'max_clicks');
        }
        if (Arr::has($data, 'max_image_changes') && $isAdmin) {
            $ad->max_image_changes = Arr::get($data, 'max_image_changes');
        }

        // Handle image changes with restrictions for non-admins
        if (Arr::has($data, 'image_url') && $data['image_url'] !== $ad->image_url) {
            if (!$isAdmin && $ad->max_image_changes !== null && $ad->image_changes_count >= $ad->max_image_changes) {
                throw new \Flarum\Foundation\ValidationException([
                    'image_url' => 'You have reached the maximum number of image changes for this ad.'
                ]);
            }

            $newImageUrl = Arr::get($data, 'image_url');
            $this->imageService->validateImageUrl($newImageUrl);

            $zone = AdZone::find($ad->zone_id);
            $processedUrl = $this->imageService->processImage(
                $newImageUrl,
                $zone ? $zone->max_width : null,
                $zone ? $zone->max_height : null
            );

            $requireApproval = (bool) $this->settings->get('ralkage-ad-management.require_image_approval', false);

            if (!$isAdmin && $requireApproval) {
                // Queue for admin review — don't touch image_url yet
                if ($ad->pending_image_url) {
                    $this->imageService->deleteCompressedImage($ad->pending_image_url);
                }
                $ad->pending_image_url = $processedUrl;
                // Count the submission now; the check already verified they have remaining changes
                $ad->image_changes_count++;
            } else {
                // Apply immediately
                if ($ad->image_url) {
                    $this->imageService->deleteCompressedImage($ad->image_url);
                }
                $ad->image_url = $processedUrl;
                $ad->image_changes_count++;
            }
        }

        if (Arr::has($data, 'width')) {
            $ad->width = Arr::get($data, 'width');
        }
        if (Arr::has($data, 'height')) {
            $ad->height = Arr::get($data, 'height');
        }

        $ad->save();

        return $ad;
    }

    public function trackClick(Ad $ad, ?User $user, ?string $ipAddress): void
    {
        $click = new AdClick();
        $click->ad_id = $ad->id;
        $click->user_id = $user ? $user->id : null;
        $click->ip_address = $ipAddress;
        $click->created_at = Carbon::now();
        $click->save();

        $ad->increment('clicks_count');
    }

    public function trackImpression(Ad $ad): void
    {
        $ad->increment('impressions_count');
    }

    public function getAnalytics(Ad $ad, ?string $period = '30d'): array
    {
        $days = (int) str_replace('d', '', $period ?: '30d');
        $since = Carbon::now()->subDays($days);

        $clicksByDay = AdClick::where('ad_id', $ad->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $periodClicks = array_sum($clicksByDay);
        $totalClicks = $ad->clicks_count;
        $totalImpressions = $ad->impressions_count;
        $ctr = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0;
        $periodCtr = $totalImpressions > 0 ? round(($periodClicks / $totalImpressions) * 100, 2) : 0;

        return [
            'total_clicks' => $totalClicks,
            'total_impressions' => $totalImpressions,
            'period_clicks' => $periodClicks,
            'ctr' => $ctr,
            'period_ctr' => $periodCtr,
            'clicks_by_day' => $clicksByDay,
            'period_days' => $days,
        ];
    }

    public function getBetweenPostsInterval(): int
    {
        return (int) $this->settings->get('ralkage-ad-management.between_posts_interval', 5);
    }

    private function validateLinkUrl(?string $url): void
    {
        if ($url === null || $url === '') {
            return;
        }

        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new ValidationException(['link_url' => 'Link URL must use http or https.']);
        }
    }
}
