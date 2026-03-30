<?php

namespace Ralkage\AdManagement\Api\Controller;

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ralkage\AdManagement\Model\Ad;
use Ralkage\AdManagement\Service\AdService;

/**
 * Flarum 2.0 compatible handler for GET /api/advertisements/active.
 * Produces a JSON:API formatted response manually since the old
 * AbstractListController is no longer available.
 */
class ListActiveAdsHandler implements RequestHandlerInterface
{
    protected $service;

    public function __construct(AdService $service)
    {
        $this->service = $service;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $user = $actor->isGuest() ? null : $actor;

        $allAds = $this->service->getAllActiveAds($user);

        $data = [];
        $included = [];

        foreach ($allAds as $zoneName => $zoneAds) {
            foreach ($zoneAds as $ad) {
                $ad->load('zone');

                $attributes = [
                    'name'             => $ad->name,
                    'type'             => $ad->type,
                    'content'          => $ad->content,
                    'imageUrl'         => $ad->image_url,
                    'linkUrl'          => $ad->link_url,
                    'altText'          => $ad->alt_text,
                    'width'            => $ad->width ? (int) $ad->width : null,
                    'height'           => $ad->height ? (int) $ad->height : null,
                    'isActive'         => (bool) $ad->is_active,
                    'status'           => $ad->status ?? ($ad->is_active ? 'active' : 'inactive'),
                    'startDate'        => $ad->start_date ? $this->formatDate($ad->start_date) : null,
                    'endDate'          => $ad->end_date ? $this->formatDate($ad->end_date) : null,
                    'priority'         => (int) $ad->priority,
                    'groupVisibility'  => $ad->group_visibility,
                    'impressionsCount' => (int) $ad->impressions_count,
                    'clicksCount'      => (int) $ad->clicks_count,
                    'createdAt'        => $ad->created_at ? $this->formatDate($ad->created_at) : null,
                ];

                if ($ad->impressions_count > 0) {
                    $attributes['ctr'] = round(($ad->clicks_count / $ad->impressions_count) * 100, 2);
                } else {
                    $attributes['ctr'] = 0;
                }

                $item = [
                    'type'       => 'advertisements',
                    'id'         => (string) $ad->id,
                    'attributes' => $attributes,
                ];

                if ($ad->zone) {
                    $item['relationships'] = [
                        'zone' => [
                            'data' => ['type' => 'ad-zones', 'id' => (string) $ad->zone->id],
                        ],
                    ];

                    $zoneId = (string) $ad->zone->id;
                    if (! isset($included[$zoneId])) {
                        $included[$zoneId] = [
                            'type'       => 'ad-zones',
                            'id'         => $zoneId,
                            'attributes' => [
                                'name'        => $ad->zone->name,
                                'label'       => $ad->zone->label,
                                'description' => $ad->zone->description,
                                'position'    => $ad->zone->position,
                                'isDefault'   => (bool) $ad->zone->is_default,
                                'isActive'    => (bool) $ad->zone->is_active,
                                'sortOrder'   => (int) $ad->zone->sort_order,
                                'displayMode' => $ad->zone->display_mode ?: 'rotate',
                            ],
                        ];
                    }
                }

                $data[] = $item;
            }
        }

        return new JsonResponse([
            'data'     => $data,
            'included' => array_values($included),
        ]);
    }

    private function formatDate($date): ?string
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format(\DateTimeInterface::ATOM);
        }

        return (string) $date;
    }
}
