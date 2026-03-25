<?php

namespace Ralkage\AdManagement\Api\Controller;

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Ralkage\AdManagement\Model\AdZone;

class DeleteAdZoneController extends AbstractDeleteController
{
    protected function delete(ServerRequestInterface $request)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $id = Arr::get($request->getQueryParams(), 'id');
        $zone = AdZone::findOrFail($id);

        if ($zone->is_default) {
            throw new \Flarum\Foundation\ValidationException([
                'zone' => 'Default zones cannot be deleted.'
            ]);
        }

        $zone->delete();
    }
}
