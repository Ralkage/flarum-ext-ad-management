<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('advertisements', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('is_active');
        });

        // Sync status with existing is_active values
        $schema->getConnection()->table('advertisements')
            ->where('is_active', false)
            ->update(['status' => 'inactive']);
    },
    'down' => function (Builder $schema) {
        $schema->table('advertisements', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    },
];
