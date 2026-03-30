<?php

namespace Ralkage\AdManagement\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;

class Ad extends AbstractModel
{
    protected $table = 'advertisements';

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'last_notified_at' => 'datetime',
    ];

    public function zone()
    {
        return $this->belongsTo(AdZone::class, 'zone_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function clicks()
    {
        return $this->hasMany(AdClick::class, 'ad_id');
    }

    public function getGroupVisibilityAttribute($value)
    {
        return $value ? json_decode($value, true) : null;
    }

    public function setGroupVisibilityAttribute($value)
    {
        $this->attributes['group_visibility'] = $value ? json_encode($value) : null;
    }

    public function isVisibleToUser($user)
    {
        $groups = $this->group_visibility;

        if (empty($groups)) {
            return true;
        }

        if (!$user) {
            return in_array('0', $groups) || in_array(0, $groups);
        }

        $userGroupIds = $user->groups->pluck('id')->toArray();

        return !empty(array_intersect($userGroupIds, $groups));
    }

    public function isCurrentlyActive()
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->start_date && $this->start_date > $now) {
            return false;
        }

        if ($this->end_date && $this->end_date < $now) {
            return false;
        }

        if ($this->max_impressions && $this->impressions_count >= $this->max_impressions) {
            return false;
        }

        if ($this->max_clicks && $this->clicks_count >= $this->max_clicks) {
            return false;
        }

        return true;
    }
}
