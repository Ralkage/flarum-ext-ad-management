<?php

namespace Ralkage\AdManagement\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ralkage\AdManagement\Model\Ad;
use Ralkage\AdManagement\Service\AdService;

class AdAnalyticsController implements RequestHandlerInterface
{
    protected $service;

    public function __construct(AdService $service)
    {
        $this->service = $service;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $id = Arr::get($request->getAttribute('routeParameters', []), 'id');
        $period = Arr::get($request->getQueryParams(), 'period', '30d');
        if (!in_array($period, ['7d', '30d', '90d'], true)) {
            $period = '30d';
        }

        $ad = Ad::findOrFail($id);

        // Non-admins can only see analytics for their own ads
        if (!$actor->isAdmin() && (int) $ad->user_id !== (int) $actor->id) {
            $actor->assertAdmin();
        }

        $analytics = $this->service->getAnalytics($ad, $period);

        return new JsonResponse(['data' => $analytics]);
    }
}
