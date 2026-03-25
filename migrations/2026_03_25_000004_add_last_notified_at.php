<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('advertisements', function (Blueprint $table) {
            $table->timestamp('last_notified_at')->nullable()->after('end_date');
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('advertisements', function (Blueprint $table) {
            $table->dropColumn('last_notified_at');
        });
    },
];
