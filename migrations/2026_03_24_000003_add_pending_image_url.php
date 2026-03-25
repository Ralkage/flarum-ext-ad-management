<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('advertisements', function (Blueprint $table) {
            $table->string('pending_image_url', 500)->nullable()->after('image_url');
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('advertisements', function (Blueprint $table) {
            $table->dropColumn('pending_image_url');
        });
    },
];
