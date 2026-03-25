<?php

namespace Ralkage\AdManagement\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Foundation\ValidationException;
use Illuminate\Database\Eloquent\Builder;
use Ralkage\AdManagement\Model\AdZone;
use Tobyz\JsonApiServer\Context as BaseContext;

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

    public function scope(Builder $query, BaseContext $context): void
    {
        $actor = $context->getActor();

        if (!$actor->isAdmin()) {
            $query->where('is_active', true);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->eagerLoadCount(['advertisements']),

            Endpoint\Show::make(),

            Endpoint\Create::make()
                ->admin(),

            Endpoint\Update::make()
                ->admin(),

            Endpoint\Delete::make()
                ->admin(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->get(fn (AdZone $zone) => $zone->name)
                ->writable(function (AdZone $zone, Context $context) {
                    return $context->creating() || (!$zone->is_default && $context->getActor()->isAdmin());
                })
                ->requiredOnCreate()
                ->maxLength(50)
                ->set(function (AdZone $zone, string $value, Context $context) {
                    $value = strtolower(trim($value));
                    if (!preg_match('/^[a-z0-9_]+$/', $value)) {
                        throw new ValidationException(['name' => 'Zone name may only contain lowercase letters, numbers, and underscores.']);
                    }
                    if (!$context->creating() && $value !== $zone->name && AdZone::where('name', $value)->exists()) {
                        throw new ValidationException(['name' => 'A zone with this name already exists.']);
                    }
                    if ($context->creating() && AdZone::where('name', $value)->exists()) {
                        throw new ValidationException(['name' => 'A zone with this name already exists.']);
                    }
                    $zone->name = $value;
                }),

            Schema\Str::make('label')
                ->get(fn (AdZone $zone) => $zone->label)
                ->writable()
                ->requiredOnCreate()
                ->maxLength(100)
                ->set(function (AdZone $zone, string $value, Context $context) {
                    $value = trim($value);
                    if ($value === '') {
                        throw new ValidationException(['label' => 'Display label is required.']);
                    }
                    $zone->label = $value;
                }),

            Schema\Str::make('description')
                ->nullable()
                ->get(fn (AdZone $zone) => $zone->description)
                ->writable(),

            Schema\Str::make('position')
                ->get(fn (AdZone $zone) => $zone->position)
                ->writable()
                ->set(function (AdZone $zone, string $value, Context $context) {
                    if (!in_array($value, self::VALID_POSITIONS, true)) {
                        throw new ValidationException(['position' => 'Invalid position. Must be one of: ' . implode(', ', self::VALID_POSITIONS)]);
                    }
                    $zone->position = $value;
                }),

            Schema\Boolean::make('isDefault')
                ->property('is_default')
                ->get(fn (AdZone $zone) => (bool) $zone->is_default),

            Schema\Boolean::make('isActive')
                ->property('is_active')
                ->get(fn (AdZone $zone) => (bool) $zone->is_active)
                ->writable()
                ->set(fn (AdZone $zone, bool $value) => $zone->is_active = $value),

            Schema\Integer::make('sortOrder')
                ->property('sort_order')
                ->get(fn (AdZone $zone) => (int) $zone->sort_order)
                ->writable()
                ->set(fn (AdZone $zone, int $value) => $zone->sort_order = $value),

            Schema\Integer::make('maxWidth')
                ->property('max_width')
                ->nullable()
                ->get(fn (AdZone $zone) => $zone->max_width ? (int) $zone->max_width : null)
                ->writable()
                ->set(fn (AdZone $zone, ?int $value) => $zone->max_width = $value ?: null),

            Schema\Integer::make('maxHeight')
                ->property('max_height')
                ->nullable()
                ->get(fn (AdZone $zone) => $zone->max_height ? (int) $zone->max_height : null)
                ->writable()
                ->set(fn (AdZone $zone, ?int $value) => $zone->max_height = $value ?: null),

            Schema\Integer::make('adsCount')
                ->property('advertisements_count')
                ->get(fn (AdZone $zone) => (int) ($zone->advertisements_count ?? 0)),

            Schema\DateTime::make('createdAt'),
        ];
    }

    public function delete(object $model, BaseContext $context): void
    {
        if ($model->is_default) {
            throw new ValidationException(['zone' => 'Default zones cannot be deleted.']);
        }

        $model->delete();
    }
}
