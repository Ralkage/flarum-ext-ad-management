<?php

namespace Ralkage\AdManagement\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;
use Ralkage\AdManagement\Api\Serializer\AdZoneSerializer;
use Ralkage\AdManagement\Model\AdZone;
use Tobscure\JsonApi\Document;

class ListAdZonesController extends AbstractListController
{
    public $serializer = AdZoneSerializer::class;

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);

        if ($actor->isAdmin()) {
            return AdZone::query()->withCount('advertisements')->orderBy('sort_order')->get();
        }

        // Users with submitAd permission may see active zones to pick from
        $actor->assertRegistered();
        if (!$actor->hasPermission('ralkage-ad-management.submitAd')) {
            $actor->assertAdmin();
        }

        return AdZone::query()->where('is_active', true)->withCount('advertisements')->orderBy('sort_order')->get();
    }
}
