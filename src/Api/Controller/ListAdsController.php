<?php

namespace Ralkage\AdManagement\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Ralkage\AdManagement\Api\Serializer\AdSerializer;
use Ralkage\AdManagement\Model\Ad;
use Tobscure\JsonApi\Document;

class ListAdsController extends AbstractListController
{
    public $serializer = AdSerializer::class;

    public $include = ['zone', 'owner'];

    public $limit = 200;

    public $maxLimit = 500;

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);

        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);

        $query = Ad::query()->with($this->extractInclude($request));

        $filter = Arr::get($request->getQueryParams(), 'filter', []);

        // Non-admins can only see their own ads
        if (!$actor->isAdmin()) {
            $actor->assertRegistered();
            $query->where('user_id', $actor->id);
        }

        if ($zoneId = Arr::get($filter, 'zone')) {
            $query->where('zone_id', $zoneId);
        }

        if (Arr::has($filter, 'active')) {
            $query->where('is_active', Arr::get($filter, 'active'));
        }

        if ($status = Arr::get($filter, 'status')) {
            $query->where('status', $status);
        }

        $total = $query->count();

        $document->addPaginationLinks(
            app('Flarum\Http\UrlGenerator')->to('api')->route('advertisements.index'),
            $request->getQueryParams(),
            $offset,
            $limit,
            $total
        );

        return $query->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get();
    }
}
