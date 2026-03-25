<?php

namespace Ralkage\AdManagement\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;

class AdSerializer extends AbstractSerializer
{
    protected $type = 'advertisements';

    protected function getDefaultAttributes($model)
    {
        $attributes = [
            'name'              => $model->name,
            'type'              => $model->type,
            'content'           => $model->content,
            'imageUrl'          => $model->image_url,
            'linkUrl'           => $model->link_url,
            'altText'           => $model->alt_text,
            'width'             => $model->width ? (int) $model->width : null,
            'height'            => $model->height ? (int) $model->height : null,
            'isActive'          => (bool) $model->is_active,
            'status'            => $model->status ?? ($model->is_active ? 'active' : 'inactive'),
            'pendingImageUrl'   => $model->pending_image_url,
            'startDate'         => $this->formatDate($model->start_date),
            'endDate'           => $this->formatDate($model->end_date),
            'priority'          => (int) $model->priority,
            'groupVisibility'   => $model->group_visibility,
            'impressionsCount'  => (int) $model->impressions_count,
            'clicksCount'       => (int) $model->clicks_count,
            'maxImpressions'    => $model->max_impressions ? (int) $model->max_impressions : null,
            'maxClicks'         => $model->max_clicks ? (int) $model->max_clicks : null,
            'imageChangesCount' => (int) $model->image_changes_count,
            'maxImageChanges'   => $model->max_image_changes ? (int) $model->max_image_changes : null,
            'createdAt'         => $this->formatDate($model->created_at),
        ];

        if ($model->impressions_count > 0) {
            $attributes['ctr'] = round(($model->clicks_count / $model->impressions_count) * 100, 2);
        } else {
            $attributes['ctr'] = 0;
        }

        return $attributes;
    }

    protected function zone($model)
    {
        return $this->hasOne($model, AdZoneSerializer::class);
    }

    protected function owner($model)
    {
        return $this->hasOne($model, BasicUserSerializer::class);
    }
}
