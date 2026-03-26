<?php

namespace Ralkage\AdManagement\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;
use Ralkage\AdManagement\Model\AdZone;

class AdZoneSerializer extends AbstractSerializer
{
    protected $type = 'ad-zones';

    protected function getDefaultAttributes($model)
    {
        return [
            'name'        => $model->name,
            'label'       => $model->label,
            'description' => $model->description,
            'position'    => $model->position,
            'isDefault'   => (bool) $model->is_default,
            'isActive'    => (bool) $model->is_active,
            'sortOrder'   => (int) $model->sort_order,
            'maxWidth'    => $model->max_width ? (int) $model->max_width : null,
            'maxHeight'   => $model->max_height ? (int) $model->max_height : null,
            'displayMode' => $model->display_mode ?: 'rotate',
            'adsCount'    => (int) ($model->advertisements_count ?? $model->advertisements()->count()),
            'createdAt'   => $this->formatDate($model->created_at),
        ];
    }
}
