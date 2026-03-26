<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('ad_zones', function (Blueprint $table) {
            $table->string('display_mode', 20)->default('rotate')->after('max_height');
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('ad_zones', function (Blueprint $table) {
            $table->dropColumn('display_mode');
        });
    },
];
