<?php

namespace Ralkage\AdManagement\Api\Controller;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Ralkage\AdManagement\Api\Serializer\AdZoneSerializer;
use Ralkage\AdManagement\Model\AdZone;
use Tobscure\JsonApi\Document;

class CreateAdZoneController extends AbstractCreateController
{
    public $serializer = AdZoneSerializer::class;

    const VALID_POSITIONS = ['header', 'below_header', 'between_posts', 'sidebar', 'above_footer', 'footer', 'custom'];

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);

        $name = trim((string) Arr::get($data, 'name', ''));
        $label = trim((string) Arr::get($data, 'label', ''));
        $position = Arr::get($data, 'position', 'custom');

        // Validate zone name: required, lowercase alphanumeric + underscores only, max 50 chars
        if ($name === '') {
            throw new ValidationException(['name' => 'Zone name is required.']);
        }
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new ValidationException(['name' => 'Zone name may only contain lowercase letters, numbers, and underscores.']);
        }
        if (strlen($name) > 50) {
            throw new ValidationException(['name' => 'Zone name may not exceed 50 characters.']);
        }
        if (AdZone::where('name', $name)->exists()) {
            throw new ValidationException(['name' => 'A zone with this name already exists.']);
        }

        // Validate label: required, max 100 chars
        if ($label === '') {
            throw new ValidationException(['label' => 'Display label is required.']);
        }
        if (strlen($label) > 100) {
            throw new ValidationException(['label' => 'Display label may not exceed 100 characters.']);
        }

        // Validate position
        if (!in_array($position, self::VALID_POSITIONS, true)) {
            throw new ValidationException(['position' => 'Invalid position value.']);
        }

        $zone = new AdZone();
        $zone->name = $name;
        $zone->label = $label;
        $zone->description = Arr::get($data, 'description');
        $zone->position = $position;
        $zone->is_default = false;
        $zone->is_active = (bool) Arr::get($data, 'isActive', true);
        $zone->sort_order = (int) Arr::get($data, 'sortOrder', 0);
        $zone->max_width = Arr::get($data, 'maxWidth') ?: null;
        $zone->max_height = Arr::get($data, 'maxHeight') ?: null;
        $zone->save();

        return $zone;
    }
}
