<?php

namespace Ralkage\AdManagement\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ralkage\AdManagement\Model\Ad;
use Ralkage\AdManagement\Service\AdService;

class TrackAdClickController implements RequestHandlerInterface
{
    // Max clicks per ad per IP per hour (prevents click fraud)
    const RATE_LIMIT = 5;

    protected $service;
    protected $cache;

    public function __construct(AdService $service, Cache $cache)
    {
        $this->service = $service;
        $this->cache = $cache;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);
        $adId = (int) Arr::get($data, 'adId');

        if (!$adId) {
            return new JsonResponse(['success' => false], 400);
        }

        $serverParams = $request->getServerParams();
        $ipAddress = Arr::get($serverParams, 'HTTP_X_FORWARDED_FOR')
            ?: Arr::get($serverParams, 'REMOTE_ADDR', '');

        // Take only the first IP if behind multiple proxies
        $ipAddress = trim(explode(',', $ipAddress)[0]);

        $hour = date('YmdH');

        // Per-IP rate limit: max RATE_LIMIT clicks per ad per hour
        $rateKey = 'ad-click-rate:' . md5($ipAddress) . ':' . $adId . ':' . $hour;
        $clickCount = (int) $this->cache->get($rateKey, 0);

        if ($clickCount >= self::RATE_LIMIT) {
            return new EmptyResponse(429);
        }

        // Per-user deduplication: one tracked click per user per ad per hour
        if (!$actor->isGuest()) {
            $dedupKey = 'ad-click-dedup:' . $actor->id . ':' . $adId . ':' . $hour;
            if ($this->cache->get($dedupKey)) {
                return new JsonResponse(['success' => true]);
            }
            $this->cache->put($dedupKey, true, 3600);
        }

        $ad = Ad::where('id', $adId)->where('is_active', true)->first();

        if (!$ad) {
            return new JsonResponse(['success' => false], 404);
        }

        $this->service->trackClick($ad, $actor->isGuest() ? null : $actor, $ipAddress);

        $this->cache->put($rateKey, $clickCount + 1, 3600);

        return new JsonResponse(['success' => true]);
    }
}
