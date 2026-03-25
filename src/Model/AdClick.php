<?php

namespace Ralkage\AdManagement\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;

class AdClick extends AbstractModel
{
    protected $table = 'ad_clicks';

    public $timestamps = false;

    protected $dates = ['created_at'];

    public function ad()
    {
        return $this->belongsTo(Ad::class, 'ad_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
