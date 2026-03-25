<?php

namespace Ralkage\AdManagement\Api\Resource;

use Carbon\Carbon;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\Message;
use Ralkage\AdManagement\Model\Ad;
use Ralkage\AdManagement\Model\AdZone;
use Ralkage\AdManagement\Service\ImageService;
use Tobyz\JsonApiServer\Context as BaseContext;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;

class AdResource extends AbstractDatabaseResource
{
    public function __construct(
        protected ImageService $imageService,
        protected SettingsRepositoryInterface $settings,
        protected Mailer $mailer
    ) {
    }

    public function type(): string
    {
        return 'advertisements';
    }

    public function model(): string
    {
        return Ad::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        $actor = $context->getActor();
        $filters = $context->queryParam('filter') ?? [];

        // Active filter is for forum display — any visitor can see active ads
        if (!empty($filters['active'])) {
            return;
        }

        // Management mode: non-admins see only their own ads
        if (!$actor->isAdmin()) {
            $query->where('user_id', $actor->id);
        }
    }

    public function newModel(BaseContext $context): object
    {
        $ad = new Ad();
        $actor = $context->getActor();

        $ad->user_id = $actor->id;
        $ad->impressions_count = 0;
        $ad->clicks_count = 0;
        $ad->image_changes_count = 0;
        $ad->priority = 0;
        $ad->max_image_changes = (int) $this->settings->get('ralkage-ad-management.default_max_image_changes', 5);

        if ($actor->isAdmin()) {
            $ad->type = 'image';
            $ad->is_active = true;
            $ad->status = 'active';
        } else {
            $ad->type = 'image';
            $ad->is_active = false;
            $ad->status = 'pending_review';
        }

        return $ad;
    }

    public function create(object $model, BaseContext $context): object
    {
        $model->save();

        $actor = $context->getActor();
        if (!$actor->isAdmin() && $model->status === 'pending_review') {
            $this->notifyAdmin($model, $actor->display_name);
        }

        return $model;
    }

    public function results(object $query, BaseContext $context): iterable
    {
        $filters = $context->queryParam('filter') ?? [];

        if (!empty($filters['active'])) {
            $actor = $context->getActor();
            $user = $actor->isGuest() ? null : $actor;

            return $query->get()->filter(fn (Ad $ad) => $ad->isVisibleToUser($user))->values();
        }

        return $query->get();
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(200, 500)
                ->eagerLoad(['zone'])
                ->defaultInclude(['zone', 'owner'])
                ->query(function (object $query, ?OffsetPagination $pagination, Context $context, array $filters): Context {
                    $actor = $context->getActor();

                    if (!empty($filters['active'])) {
                        // Forum display: return all active ads visible to this user
                        $query->where('is_active', true)
                            ->where('status', 'active')
                            ->whereHas('zone', fn ($q) => $q->where('is_active', true))
                            ->where(function ($q) {
                                $q->whereNull('start_date')->orWhere('start_date', '<=', Carbon::now());
                            })
                            ->where(function ($q) {
                                $q->whereNull('end_date')->orWhere('end_date', '>=', Carbon::now());
                            })
                            ->where(function ($q) {
                                $q->whereNull('max_impressions')->orWhereColumn('impressions_count', '<', 'max_impressions');
                            })
                            ->where(function ($q) {
                                $q->whereNull('max_clicks')->orWhereColumn('clicks_count', '<', 'max_clicks');
                            })
                            ->orderByDesc('priority');
                    } else {
                        // Management mode
                        if (!$actor->isAdmin()) {
                            $actor->assertRegistered();
                        }

                        if (!empty($filters['zone'])) {
                            $query->where('zone_id', $filters['zone']);
                        }

                        if (!empty($filters['status'])) {
                            $query->where('status', $filters['status']);
                        }

                        $query->orderByDesc('priority')->orderByDesc('created_at');
                    }

                    if ($pagination) {
                        $pagination->apply($query);
                    }

                    return $context->withQuery($query);
                }),

            Endpoint\Show::make()
                ->authenticated()
                ->can(function (Ad $ad, Context $context) {
                    $actor = $context->getActor();
                    if ($actor->isAdmin()) {
                        return null;
                    }
                    return (int) $ad->user_id === (int) $actor->id ? null : 'administrate';
                })
                ->defaultInclude(['zone', 'owner']),

            Endpoint\Create::make()
                ->authenticated()
                ->can(function (Context $context) {
                    $actor = $context->getActor();
                    if ($actor->isAdmin()) {
                        return null;
                    }
                    return $actor->hasPermission('ralkage-ad-management.submitAd') ? null : 'administrate';
                })
                ->defaultInclude(['zone', 'owner']),

            Endpoint\Update::make()
                ->authenticated()
                ->can(function (Ad $ad, Context $context) {
                    $actor = $context->getActor();
                    if ($actor->isAdmin()) {
                        return null;
                    }
                    return (int) $ad->user_id === (int) $actor->id ? null : 'administrate';
                })
                ->defaultInclude(['zone', 'owner']),

            Endpoint\Delete::make()
                ->admin(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->writable()
                ->requiredOnCreate(),

            Schema\Str::make('type')
                ->get(fn (Ad $ad) => $ad->type ?? 'image')
                ->writable(fn (Ad $ad, Context $context) => $context->getActor()->isAdmin()),

            Schema\Str::make('content')
                ->nullable()
                ->writable(fn (Ad $ad, Context $context) => $context->getActor()->isAdmin()),

            Schema\Str::make('imageUrl')
                ->property('image_url')
                ->nullable()
                ->writable()
                ->set(function (Ad $ad, ?string $value, Context $context) {
                    if ($value === null || $value === $ad->image_url) {
                        return;
                    }

                    $actor = $context->getActor();

                    if (
                        !$actor->isAdmin()
                        && $context->updating()
                        && $ad->max_image_changes !== null
                        && $ad->image_changes_count >= $ad->max_image_changes
                    ) {
                        throw new ValidationException([
                            'imageUrl' => 'You have reached the maximum number of image changes for this ad.',
                        ]);
                    }

                    $this->imageService->validateImageUrl($value);
                    $zone = $ad->zone_id ? AdZone::find($ad->zone_id) : null;
                    $processedUrl = $this->imageService->processImage(
                        $value,
                        $zone?->max_width,
                        $zone?->max_height
                    );

                    $requireApproval = (bool) $this->settings->get('ralkage-ad-management.require_image_approval', false);

                    if (!$actor->isAdmin() && $requireApproval && $context->updating()) {
                        if ($ad->pending_image_url) {
                            $this->imageService->deleteCompressedImage($ad->pending_image_url);
                        }
                        $ad->pending_image_url = $processedUrl;
                        $ad->image_changes_count++;
                    } else {
                        if ($ad->image_url) {
                            $this->imageService->deleteCompressedImage($ad->image_url);
                        }
                        $ad->image_url = $processedUrl;
                        $ad->image_changes_count++;
                    }
                }),

            Schema\Str::make('pendingImageUrl')
                ->property('pending_image_url')
                ->nullable()
                ->get(fn (Ad $ad) => $ad->pending_image_url),

            Schema\Str::make('pendingImageAction')
                ->property('pending_image_action')
                ->nullable()
                ->visible(false)
                ->writable(fn (Ad $ad, Context $context) => $context->updating() && $context->getActor()->isAdmin())
                ->set(function (Ad $ad, ?string $value, Context $context) {
                    if ($value === 'approve' && $ad->pending_image_url) {
                        if ($ad->image_url) {
                            $this->imageService->deleteCompressedImage($ad->image_url);
                        }
                        $ad->image_url = $ad->pending_image_url;
                        $ad->pending_image_url = null;
                    } elseif ($value === 'reject' && $ad->pending_image_url) {
                        $this->imageService->deleteCompressedImage($ad->pending_image_url);
                        $ad->pending_image_url = null;
                    }
                }),

            Schema\Str::make('linkUrl')
                ->property('link_url')
                ->nullable()
                ->writable()
                ->set(function (Ad $ad, ?string $value, Context $context) {
                    if ($value !== null && $value !== '') {
                        $scheme = strtolower(parse_url($value, PHP_URL_SCHEME) ?? '');
                        if (!in_array($scheme, ['http', 'https'], true)) {
                            throw new ValidationException(['linkUrl' => 'Link URL must use http or https.']);
                        }
                    }
                    $ad->link_url = $value;
                }),

            Schema\Str::make('altText')
                ->property('alt_text')
                ->nullable()
                ->writable(),

            Schema\Integer::make('width')
                ->nullable()
                ->writable(),

            Schema\Integer::make('height')
                ->nullable()
                ->writable(),

            Schema\Boolean::make('isActive')
                ->property('is_active')
                ->get(fn (Ad $ad) => (bool) $ad->is_active)
                ->writable(fn (Ad $ad, Context $context) => $context->getActor()->isAdmin())
                ->set(function (Ad $ad, bool $value, Context $context) {
                    $ad->is_active = $value;
                    if ($value) {
                        $ad->status = 'active';
                    } elseif (!in_array($ad->status, ['pending_review', 'rejected'])) {
                        $ad->status = 'inactive';
                    }
                }),

            Schema\Str::make('status')
                ->get(fn (Ad $ad) => $ad->status ?? ($ad->is_active ? 'active' : 'inactive'))
                ->writable(fn (Ad $ad, Context $context) => $context->getActor()->isAdmin())
                ->set(function (Ad $ad, string $value, Context $context) {
                    if (in_array($value, ['active', 'inactive', 'rejected'], true)) {
                        $ad->status = $value;
                        $ad->is_active = ($value === 'active');
                    }
                }),

            Schema\Integer::make('priority')
                ->get(fn (Ad $ad) => (int) $ad->priority)
                ->writable(fn (Ad $ad, Context $context) => $context->getActor()->isAdmin()),

            Schema\Str::make('groupVisibility')
                ->property('group_visibility')
                ->nullable()
                ->get(fn (Ad $ad) => $ad->group_visibility)
                ->writable(fn (Ad $ad, Context $context) => $context->getActor()->isAdmin()),

            Schema\Integer::make('impressionsCount')
                ->property('impressions_count')
                ->get(fn (Ad $ad) => (int) $ad->impressions_count),

            Schema\Integer::make('clicksCount')
                ->property('clicks_count')
                ->get(fn (Ad $ad) => (int) $ad->clicks_count),

            Schema\Number::make('ctr')
                ->get(fn (Ad $ad) => $ad->impressions_count > 0
                    ? round(($ad->clicks_count / $ad->impressions_count) * 100, 2)
                    : 0),

            Schema\Integer::make('maxImpressions')
                ->property('max_impressions')
                ->nullable()
                ->writable(fn (Ad $ad, Context $context) => $context->getActor()->isAdmin()),

            Schema\Integer::make('maxClicks')
                ->property('max_clicks')
                ->nullable()
                ->writable(fn (Ad $ad, Context $context) => $context->getActor()->isAdmin()),

            Schema\Integer::make('imageChangesCount')
                ->property('image_changes_count')
                ->get(fn (Ad $ad) => (int) $ad->image_changes_count),

            Schema\Integer::make('maxImageChanges')
                ->property('max_image_changes')
                ->nullable()
                ->writable(fn (Ad $ad, Context $context) => $context->getActor()->isAdmin()),

            Schema\DateTime::make('startDate')
                ->property('start_date')
                ->nullable()
                ->writable(fn (Ad $ad, Context $context) => $context->getActor()->isAdmin()),

            Schema\DateTime::make('endDate')
                ->property('end_date')
                ->nullable()
                ->writable(fn (Ad $ad, Context $context) => $context->getActor()->isAdmin()),

            Schema\DateTime::make('createdAt'),

            Schema\DateTime::make('lastNotifiedAt')
                ->property('last_notified_at'),

            // Relationships
            Schema\Relationship\ToOne::make('zone')
                ->type('ad-zones')
                ->includable()
                ->writable()
                ->set(fn (Ad $ad, ?AdZone $zone) => $ad->zone_id = $zone?->id),

            Schema\Relationship\ToOne::make('owner')
                ->type('users')
                ->includable()
                ->writable(fn (Ad $ad, Context $context) => $context->creating() && $context->getActor()->isAdmin())
                ->set(fn (Ad $ad, ?User $user, Context $context) => $ad->user_id = $user?->id ?? $context->getActor()->id),
        ];
    }

    private function notifyAdmin(Ad $ad, string $ownerName): void
    {
        $adminEmail = $this->settings->get('mail_from', '');
        if (!$adminEmail) {
            return;
        }

        $forumTitle = $this->settings->get('forum_title', 'Forum');
        $forumUrl = rtrim((string) $this->settings->get('url', ''), '/');
        $adName = $ad->name;

        $body = "Hello,\n\nA new advertisement \"{$adName}\" has been submitted by {$ownerName} and is awaiting review.\n\nTo review it, visit the Ad Management panel:\n{$forumUrl}/admin\n\n{$forumTitle}";
        $subject = "[{$forumTitle}] New ad pending review: \"{$adName}\"";

        try {
            $this->mailer->raw($body, function (Message $message) use ($adminEmail, $subject) {
                $message->to($adminEmail);
                $message->subject($subject);
            });
        } catch (\Exception $e) {
            // Don't fail the request if the notification email fails
        }
    }
}
