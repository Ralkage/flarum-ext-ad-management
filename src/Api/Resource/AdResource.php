<?php

namespace Ralkage\AdManagement\Api\Resource;

use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\Message;
use Illuminate\Support\Arr;
use Ralkage\AdManagement\Model\Ad;
use Ralkage\AdManagement\Model\AdZone;
use Ralkage\AdManagement\Service\AdService;
use Ralkage\AdManagement\Service\ImageService;
use Tobyz\JsonApiServer\Context;

/**
 * @extends AbstractDatabaseResource<Ad>
 */
class AdResource extends AbstractDatabaseResource
{
    public function __construct(
        protected AdService $service,
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

    public function scope(Builder $query, Context $context): void
    {
        $actor = $context->getActor();

        if (! $actor->isAdmin()) {
            $query->where('user_id', $actor->id);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->defaultInclude(['zone', 'owner'])
                ->paginate(200)
                ->defaultSort('-priority,-createdAt'),
            Endpoint\Show::make()
                ->authenticated()
                ->defaultInclude(['zone', 'owner']),
            Endpoint\Create::make()
                ->authenticated(),
            Endpoint\Update::make()
                ->authenticated(),
            Endpoint\Delete::make()
                ->authenticated(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('priority'),
            SortColumn::make('createdAt'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->requiredOnCreate()
                ->writable(),
            Schema\Str::make('type')
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Str::make('content')
                ->nullable()
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Str::make('imageUrl')
                ->property('image_url')
                ->nullable()
                ->writable(),
            Schema\Str::make('linkUrl')
                ->property('link_url')
                ->nullable()
                ->writable(),
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
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Str::make('status')
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Str::make('pendingImageUrl')
                ->property('pending_image_url'),
            Schema\DateTime::make('startDate')
                ->property('start_date')
                ->nullable()
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\DateTime::make('endDate')
                ->property('end_date')
                ->nullable()
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Integer::make('priority')
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Arr::make('groupVisibility')
                ->property('group_visibility')
                ->nullable()
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin())
                ->get(fn (Ad $ad) => $ad->group_visibility),
            Schema\Integer::make('impressionsCount')
                ->property('impressions_count'),
            Schema\Integer::make('clicksCount')
                ->property('clicks_count'),
            Schema\Integer::make('maxImpressions')
                ->property('max_impressions')
                ->nullable()
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Integer::make('maxClicks')
                ->property('max_clicks')
                ->nullable()
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Integer::make('imageChangesCount')
                ->property('image_changes_count'),
            Schema\Integer::make('maxImageChanges')
                ->property('max_image_changes')
                ->nullable()
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\Number::make('ctr')
                ->get(function (Ad $ad) {
                    if ($ad->impressions_count > 0) {
                        return round(($ad->clicks_count / $ad->impressions_count) * 100, 2);
                    }
                    return 0;
                }),

            // Virtual: carries an admin's approve/reject of a member's replacement
            // image. Applied in saving(), after the image-change handling below,
            // so that promoting a pending image is not itself counted as a change.
            Schema\Str::make('pendingImageAction')
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin())
                ->hidden()
                ->set(fn () => null),

            Schema\Relationship\ToOne::make('zone')
                ->type('ad-zones')
                ->writable()
                ->requiredOnCreate()
                ->includable(),
            Schema\Relationship\ToOne::make('owner')
                ->type('users')
                ->includable(),
        ];
    }

    /**
     * Approve or reject an image a member has submitted to replace the live one.
     */
    protected function applyPendingImageAction(Ad $ad, ?string $action): void
    {
        if ($action === 'approve' && $ad->pending_image_url) {
            if ($ad->image_url) {
                $this->imageService->deleteCompressedImage($ad->image_url);
            }
            $ad->image_url = $ad->pending_image_url;
            $ad->pending_image_url = null;
        } elseif ($action === 'reject') {
            if ($ad->pending_image_url) {
                $this->imageService->deleteCompressedImage($ad->pending_image_url);
            }
            $ad->pending_image_url = null;
        }
    }

    public function creating(object $model, Context $context): ?object
    {
        $actor = $context->getActor();
        $body = $context->body();
        $data = Arr::get($body, 'data.attributes', []);

        // Permission check
        if (! $actor->isAdmin() && ! $actor->hasPermission('ralkage-ad-management.submitAd')) {
            $actor->assertAdmin();
        }

        if ($actor->isAdmin()) {
            $model->user_id = Arr::get($data, 'user_id', $actor->id);
            if (! $model->type) {
                $model->type = 'image';
            }
            if ($model->status === null) {
                $model->status = $model->is_active ? 'active' : 'inactive';
            }
        } else {
            $model->user_id = $actor->id;
            $model->type = 'image';
            $model->is_active = false;
            $model->status = 'pending_review';
            $model->priority = 0;
            $model->max_image_changes = (int) $this->settings->get(
                'ralkage-ad-management.default_max_image_changes', 5
            );
        }

        // Process image
        if ($model->image_url && $model->type === 'image') {
            $this->imageService->validateImageUrl($model->image_url);
            $zone = AdZone::find($model->zone_id);
            $model->image_url = $this->imageService->processImage(
                $model->image_url,
                $zone ? $zone->max_width : null,
                $zone ? $zone->max_height : null
            );
        }

        // Validate link URL
        $this->validateLinkUrl($model->link_url);

        return $model;
    }

    public function create(object $model, Context $context): object
    {
        $this->saveModel($model, $context);

        // Send admin notification for non-admin submissions
        $actor = $context->getActor();
        if (! $actor->isAdmin() && $model->status === 'pending_review') {
            $this->notifyAdmin($model, $actor->display_name);
        }

        return $model;
    }

    public function saving(object $model, Context $context): ?object
    {
        if ($context->creating(self::class)) {
            return $model;
        }

        $actor = $context->getActor();
        $body = $context->body();
        $data = Arr::get($body, 'data.attributes', []);
        $isAdmin = $actor->isAdmin();

        // Ownership check for non-admins
        if (! $isAdmin && (int) $model->user_id !== (int) $actor->id) {
            $actor->assertAdmin();
        }

        // Handle status change
        if (Arr::has($data, 'status') && $isAdmin) {
            $status = Arr::get($data, 'status');
            if (in_array($status, ['active', 'inactive', 'rejected'], true)) {
                $model->status = $status;
                $model->is_active = ($status === 'active');
            }
        } elseif ($model->isDirty('is_active') && $isAdmin) {
            if ($model->is_active) {
                $model->status = 'active';
            } elseif (! in_array($model->status, ['pending_review', 'rejected'])) {
                $model->status = 'inactive';
            }
        }

        // Handle image changes
        if ($model->isDirty('image_url') && $model->image_url !== $model->getOriginal('image_url')) {
            $newUrl = $model->image_url;

            if (! $isAdmin && $model->max_image_changes !== null
                && $model->image_changes_count >= $model->max_image_changes) {
                throw new ValidationException([
                    'image_url' => 'You have reached the maximum number of image changes for this ad.'
                ]);
            }

            $this->imageService->validateImageUrl($newUrl);
            $zone = AdZone::find($model->zone_id);
            $processedUrl = $this->imageService->processImage(
                $newUrl,
                $zone ? $zone->max_width : null,
                $zone ? $zone->max_height : null
            );

            $requireApproval = (bool) $this->settings->get('ralkage-ad-management.require_image_approval', false);

            if (! $isAdmin && $requireApproval) {
                $model->image_url = $model->getOriginal('image_url');
                if ($model->pending_image_url) {
                    $this->imageService->deleteCompressedImage($model->pending_image_url);
                }
                $model->pending_image_url = $processedUrl;
                $model->image_changes_count++;
            } else {
                if ($model->getOriginal('image_url')) {
                    $this->imageService->deleteCompressedImage($model->getOriginal('image_url'));
                }
                $model->image_url = $processedUrl;
                $model->image_changes_count++;
            }
        }

        // Handle pending image action (admin approve/reject). Deliberately after the
        // image-change block above: promoting a pending image makes image_url dirty,
        // and running this first would re-process it and count it as another change.
        if ($isAdmin && Arr::has($data, 'pendingImageAction')) {
            $this->applyPendingImageAction($model, Arr::get($data, 'pendingImageAction'));
        }

        // Validate link URL
        if ($model->isDirty('link_url')) {
            $this->validateLinkUrl($model->link_url);
        }

        return $model;
    }

    public function deleting(object $model, Context $context): void
    {
        $context->getActor()->assertAdmin();
    }

    private function validateLinkUrl(?string $url): void
    {
        if ($url === null || $url === '') {
            return;
        }

        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new ValidationException(['link_url' => 'Link URL must use http or https.']);
        }
    }

    private function notifyAdmin(Ad $ad, string $ownerName): void
    {
        $adminEmail = $this->settings->get('mail_from', '');
        if (! $adminEmail) {
            return;
        }

        $forumTitle = $this->settings->get('forum_title', 'Forum');
        $forumUrl = rtrim((string) $this->settings->get('url', ''), '/');

        $body = "Hello,\n\nA new advertisement \"{$ad->name}\" has been submitted by {$ownerName} and is awaiting review.\n\nTo review it, visit the Ad Management panel:\n{$forumUrl}/admin\n\n{$forumTitle}";
        $subject = "[{$forumTitle}] New ad pending review: \"{$ad->name}\"";

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
