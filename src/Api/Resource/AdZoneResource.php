<?php

namespace Ralkage\AdManagement\Api\Resource;

use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Foundation\ValidationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Ralkage\AdManagement\Model\AdZone;
use Tobyz\JsonApiServer\Context;

/**
 * @extends AbstractDatabaseResource<AdZone>
 */
class AdZoneResource extends AbstractDatabaseResource
{
    const VALID_POSITIONS = ['header', 'below_header', 'between_posts', 'sidebar', 'above_footer', 'footer', 'custom'];

    public function type(): string
    {
        return 'ad-zones';
    }

    public function model(): string
    {
        return AdZone::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $actor = $context->getActor();

        if (! $actor->isAdmin()) {
            $query->where('is_active', true);
        }

        if ($context->listing(self::class)) {
            $query->withCount('advertisements')->orderBy('sort_order');
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make(),
            Endpoint\Show::make(),
            Endpoint\Create::make()
                ->authenticated()
                ->can('administrate'),
            Endpoint\Update::make()
                ->authenticated()
                ->can('administrate'),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('administrate'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->requiredOnCreate()
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Str::make('label')
                ->requiredOnCreate()
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Str::make('description')
                ->nullable()
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Str::make('position')
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Boolean::make('isDefault')
                ->property('is_default'),
            Schema\Boolean::make('isActive')
                ->property('is_active')
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Integer::make('sortOrder')
                ->property('sort_order')
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Integer::make('maxWidth')
                ->property('max_width')
                ->nullable()
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Integer::make('maxHeight')
                ->property('max_height')
                ->nullable()
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Str::make('displayMode')
                ->property('display_mode')
                ->writable(fn ($model, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Integer::make('adsCount')
                ->get(fn (AdZone $zone) => (int) ($zone->advertisements_count ?? $zone->advertisements()->count())),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        $body = $context->body();
        $data = Arr::get($body, 'data.attributes', []);

        $this->validateName($model->name, null);
        $this->validateLabel($model->label);
        $this->validatePosition($model->position ?? 'custom');

        if (! $model->position) {
            $model->position = 'custom';
        }
        $model->is_default = false;

        if (! $model->display_mode || ! in_array($model->display_mode, ['rotate', 'stack'], true)) {
            $model->display_mode = 'rotate';
        }

        return $model;
    }

    public function saving(object $model, Context $context): ?object
    {
        if ($context->creating(self::class)) {
            return $model;
        }

        // Validate name changes for non-default zones
        if ($model->isDirty('name')) {
            if ($model->is_default) {
                $model->name = $model->getOriginal('name');
            } else {
                $this->validateName($model->name, $model->getOriginal('name'));
            }
        }

        if ($model->isDirty('label')) {
            $this->validateLabel($model->label);
        }

        if ($model->isDirty('position')) {
            $this->validatePosition($model->position);
        }

        if ($model->isDirty('display_mode')) {
            if (! in_array($model->display_mode, ['rotate', 'stack'], true)) {
                $model->display_mode = 'rotate';
            }
        }

        return $model;
    }

    public function deleting(object $model, Context $context): void
    {
        if ($model->is_default) {
            throw new ValidationException([
                'zone' => 'Default zones cannot be deleted.'
            ]);
        }
    }

    private function validateName(?string $name, ?string $originalName): void
    {
        $name = trim((string) $name);

        if ($name === '') {
            throw new ValidationException(['name' => 'Zone name is required.']);
        }
        if (! preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new ValidationException(['name' => 'Zone name may only contain lowercase letters, numbers, and underscores.']);
        }
        if (strlen($name) > 50) {
            throw new ValidationException(['name' => 'Zone name may not exceed 50 characters.']);
        }
        if ($name !== $originalName && AdZone::where('name', $name)->exists()) {
            throw new ValidationException(['name' => 'A zone with this name already exists.']);
        }
    }

    private function validateLabel(?string $label): void
    {
        $label = trim((string) $label);

        if ($label === '') {
            throw new ValidationException(['label' => 'Display label is required.']);
        }
        if (strlen($label) > 100) {
            throw new ValidationException(['label' => 'Display label may not exceed 100 characters.']);
        }
    }

    private function validatePosition(string $position): void
    {
        if (! in_array($position, self::VALID_POSITIONS, true)) {
            throw new ValidationException(['position' => 'Invalid position value.']);
        }
    }
}
