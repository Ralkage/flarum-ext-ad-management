<?php

namespace Ralkage\AdManagement\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

class AdZone extends AbstractModel
{
    protected $table = 'ad_zones';

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function advertisements()
    {
        return $this->hasMany(Ad::class, 'zone_id');
    }

    public function activeAds()
    {
        return $this->hasMany(Ad::class, 'zone_id')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('start_date')->orWhere('start_date', '<=', Carbon::now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', Carbon::now());
            })
            ->where(function ($query) {
                $query->whereNull('max_impressions')
                    ->orWhereColumn('impressions_count', '<', 'max_impressions');
            })
            ->where(function ($query) {
                $query->whereNull('max_clicks')
                    ->orWhereColumn('clicks_count', '<', 'max_clicks');
            })
            ->orderByDesc('priority');
    }
}
