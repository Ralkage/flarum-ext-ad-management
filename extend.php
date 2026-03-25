<?php

namespace Ralkage\AdManagement;

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Extend;
use Ralkage\AdManagement\Api\Controller;
use s9e\TextFormatter\Configurator;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->route('/ads', 'ads'),

    new Extend\Locales(__DIR__.'/locale'),

    // Post shortcode: {myadvertisements[zone_name]} → AdZonePlaceholder div
    (new Extend\Formatter)
        ->configure(function (Configurator $configurator) {
            $tag = $configurator->tags->add('ADZONE');
            $tag->rules->autoClose();
            $tag->attributes->add('zone');
            $tag->template = '<div class="AdZonePlaceholder" data-zone="{@zone}"></div>';
            $configurator->BBCodes->add('ADZONE', ['defaultAttribute' => 'zone']);
        })
        ->parse(function ($parser, $context, string $text): string {
            return preg_replace(
                '/\{myadvertisements\[([a-z][a-z0-9_-]*)\]\}/i',
                '[adzone=$1]',
                $text
            ) ?? $text;
        }),

    (new Extend\Settings())
        ->default('ralkage-ad-management.between_posts_interval', 5)
        ->default('ralkage-ad-management.default_max_image_changes', 5)
        ->default('ralkage-ad-management.track_impressions', true)
        ->default('ralkage-ad-management.track_clicks', true)
        ->default('ralkage-ad-management.hide_ads_for_groups', '')
        ->default('ralkage-ad-management.adsense_publisher_id', '')
        ->default('ralkage-ad-management.allowed_image_formats', 'jpg,jpeg,png,webp,gif')
        ->default('ralkage-ad-management.enable_compression', false)
        ->default('ralkage-ad-management.compression_quality', 85)
        ->default('ralkage-ad-management.compression_method', 'gd')
        ->default('ralkage-ad-management.require_image_approval', false)
        ->default('ralkage-ad-management.expiration_reminder_days', 7)
        ->default('ralkage-ad-management.send_performance_reports', false)
        ->default('ralkage-ad-management.expiration_subject_template', '')
        ->default('ralkage-ad-management.expiration_body_template', '')
        ->default('ralkage-ad-management.performance_subject_template', '')
        ->default('ralkage-ad-management.performance_body_template', '')
        ->serializeToForum('adsBetweenPostsInterval', 'ralkage-ad-management.between_posts_interval', 'intval')
        ->serializeToForum('adsTrackImpressions', 'ralkage-ad-management.track_impressions', function ($value) {
            return (bool) $value;
        })
        ->serializeToForum('adsTrackClicks', 'ralkage-ad-management.track_clicks', function ($value) {
            return (bool) $value;
        })
        ->serializeToForum('adsHideForGroups', 'ralkage-ad-management.hide_ads_for_groups'),

    // API routes for ad management
    (new Extend\Routes('api'))
        // Ad zones
        ->get('/ad-zones', 'ad-zones.index', Controller\ListAdZonesController::class)
        ->post('/ad-zones', 'ad-zones.create', Controller\CreateAdZoneController::class)
        ->patch('/ad-zones/{id}', 'ad-zones.update', Controller\UpdateAdZoneController::class)
        ->delete('/ad-zones/{id}', 'ad-zones.delete', Controller\DeleteAdZoneController::class)
        // Advertisements - static routes MUST come before parameterized routes
        ->get('/advertisements', 'advertisements.index', Controller\ListAdsController::class)
        ->get('/advertisements/active', 'advertisements.active', Controller\ListActiveAdsController::class)
        ->post('/advertisements', 'advertisements.create', Controller\CreateAdController::class)
        ->patch('/advertisements/{id}', 'advertisements.update', Controller\UpdateAdController::class)
        ->delete('/advertisements/{id}', 'advertisements.delete', Controller\DeleteAdController::class)
        // Tracking
        ->post('/ad-track/click', 'ad-track.click', Controller\TrackAdClickController::class)
        ->post('/ad-track/impression', 'ad-track.impression', Controller\TrackAdImpressionController::class)
        // Analytics
        ->get('/advertisements/{id}/analytics', 'advertisements.analytics', Controller\AdAnalyticsController::class),

    (new Extend\ApiSerializer(ForumSerializer::class))
        ->attributes(function ($serializer, $model, $attributes) {
            $actor = $serializer->getActor();
            $attributes['canManageAds'] = $actor->isAdmin();
            $attributes['canViewOwnAds'] = !$actor->isGuest();
            $attributes['canSubmitAds'] = !$actor->isGuest() && ($actor->isAdmin() || $actor->hasPermission('ralkage-ad-management.submitAd'));
            return $attributes;
        }),

    // Console commands
    (new Extend\Console())
        ->command(Command\SendAdNotificationsCommand::class)
        ->command(Command\PurgeAdClicksCommand::class),
];
