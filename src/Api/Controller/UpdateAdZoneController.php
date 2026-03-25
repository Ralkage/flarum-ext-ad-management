<?php

namespace Ralkage\AdManagement\Api\Controller;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Ralkage\AdManagement\Api\Serializer\AdZoneSerializer;
use Ralkage\AdManagement\Model\AdZone;
use Tobscure\JsonApi\Document;

class UpdateAdZoneController extends AbstractShowController
{
    public $serializer = AdZoneSerializer::class;

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $id = Arr::get($request->getQueryParams(), 'id');
        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);

        $zone = AdZone::findOrFail($id);

        // Only allow name changes for non-default zones
        if (Arr::has($data, 'name') && !$zone->is_default) {
            $name = trim((string) Arr::get($data, 'name', ''));

            if ($name === '') {
                throw new ValidationException(['name' => 'Zone name is required.']);
            }
            if (!preg_match('/^[a-z0-9_]+$/', $name)) {
                throw new ValidationException(['name' => 'Zone name may only contain lowercase letters, numbers, and underscores.']);
            }
            if (strlen($name) > 50) {
                throw new ValidationException(['name' => 'Zone name may not exceed 50 characters.']);
            }
            if ($name !== $zone->name && AdZone::where('name', $name)->exists()) {
                throw new ValidationException(['name' => 'A zone with this name already exists.']);
            }

            $zone->name = $name;
        }

        if (Arr::has($data, 'label')) {
            $label = trim((string) Arr::get($data, 'label', ''));
            if ($label === '') {
                throw new ValidationException(['label' => 'Display label is required.']);
            }
            if (strlen($label) > 100) {
                throw new ValidationException(['label' => 'Display label may not exceed 100 characters.']);
            }
            $zone->label = $label;
        }
        if (Arr::has($data, 'description')) {
            $zone->description = Arr::get($data, 'description');
        }
        if (Arr::has($data, 'isActive')) {
            $zone->is_active = Arr::get($data, 'isActive');
        }
        if (Arr::has($data, 'sortOrder')) {
            $zone->sort_order = Arr::get($data, 'sortOrder');
        }
        if (Arr::has($data, 'maxWidth')) {
            $zone->max_width = Arr::get($data, 'maxWidth') ?: null;
        }
        if (Arr::has($data, 'maxHeight')) {
            $zone->max_height = Arr::get($data, 'maxHeight') ?: null;
        }

        $zone->save();

        return $zone;
    }
}
