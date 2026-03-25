<?php

namespace Ralkage\AdManagement\Api\Controller;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Ralkage\AdManagement\Api\Serializer\AdSerializer;
use Ralkage\AdManagement\Model\Ad;
use Ralkage\AdManagement\Service\AdService;
use Tobscure\JsonApi\Document;

class UpdateAdController extends AbstractShowController
{
    public $serializer = AdSerializer::class;

    public $include = ['zone', 'owner'];

    protected $service;

    public function __construct(AdService $service)
    {
        $this->service = $service;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $id = Arr::get($request->getQueryParams(), 'id');
        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);

        $ad = Ad::findOrFail($id);

        // Non-admins can only edit their own ads
        if (!$actor->isAdmin() && (int) $ad->user_id !== (int) $actor->id) {
            $actor->assertAdmin();
        }

        return $this->service->updateAd($ad, $data, $actor);
    }
}
