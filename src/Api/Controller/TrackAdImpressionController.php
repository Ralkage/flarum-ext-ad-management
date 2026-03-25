<?php

namespace Ralkage\AdManagement\Api\Controller;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ralkage\AdManagement\Model\Ad;
use Ralkage\AdManagement\Service\AdService;

class TrackAdImpressionController implements RequestHandlerInterface
{
    const MAX_ADS_PER_REQUEST = 20;

    // Max impression batches per IP per hour (prevents spam)
    const RATE_LIMIT = 60;

    protected $service;
    protected $cache;

    public function __construct(AdService $service, Cache $cache)
    {
        $this->service = $service;
        $this->cache = $cache;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);
        $adIds = Arr::get($data, 'adIds', []);

        if (!is_array($adIds)) {
            return new JsonResponse(['success' => false], 400);
        }

        $serverParams = $request->getServerParams();
        $ipAddress = Arr::get($serverParams, 'HTTP_X_FORWARDED_FOR')
            ?: Arr::get($serverParams, 'REMOTE_ADDR', '');
        $ipAddress = trim(explode(',', $ipAddress)[0]);

        $hour = date('YmdH');

        // Rate limit: max RATE_LIMIT impression batches per IP per hour
        $rateKey = 'ad-impression-rate:' . md5($ipAddress) . ':' . $hour;
        $batchCount = (int) $this->cache->get($rateKey, 0);

        if ($batchCount >= self::RATE_LIMIT) {
            return new EmptyResponse(429);
        }

        // Cap array size to prevent abuse
        $adIds = array_slice(array_unique(array_map('intval', $adIds)), 0, self::MAX_ADS_PER_REQUEST);

        foreach ($adIds as $adId) {
            if ($adId > 0) {
                Ad::where('id', $adId)->where('is_active', true)->increment('impressions_count');
            }
        }

        $this->cache->put($rateKey, $batchCount + 1, 3600);

        return new JsonResponse(['success' => true]);
    }
}
