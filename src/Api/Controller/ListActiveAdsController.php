<?php

namespace Ralkage\AdManagement\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\Collection;
use Psr\Http\Message\ServerRequestInterface;
use Ralkage\AdManagement\Api\Serializer\AdSerializer;
use Ralkage\AdManagement\Service\AdService;
use Tobscure\JsonApi\Document;

class ListActiveAdsController extends AbstractListController
{
    public $serializer = AdSerializer::class;

    public $include = ['zone'];

    protected $service;

    public function __construct(AdService $service)
    {
        $this->service = $service;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $user = $actor->isGuest() ? null : $actor;

        $allAds = $this->service->getAllActiveAds($user);

        // Flatten into a single collection and eager-load zone relationship
        $ads = new Collection();
        foreach ($allAds as $zoneName => $zoneAds) {
            foreach ($zoneAds as $ad) {
                $ads->push($ad);
            }
        }

        // Ensure zone relationship is loaded for JSON:API included data
        $ads->load('zone');

        return $ads->all();
    }
}
