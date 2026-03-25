<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('ad_zones', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 50)->unique();
            $table->string('label', 100);
            $table->text('description')->nullable();
            $table->string('position', 50);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('max_width')->nullable();
            $table->unsignedInteger('max_height')->nullable();
            $table->timestamps();
        });

        // Seed default zones
        $now = date('Y-m-d H:i:s');
        $schema->getConnection()->table('ad_zones')->insert([
            ['name' => 'header', 'label' => 'Header', 'description' => 'Top of every page, above the navigation bar', 'position' => 'header', 'is_default' => true, 'is_active' => true, 'sort_order' => 1, 'max_width' => 728, 'max_height' => 90, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'below_header', 'label' => 'Below Header', 'description' => 'Below the navigation bar, above content', 'position' => 'below_header', 'is_default' => true, 'is_active' => true, 'sort_order' => 2, 'max_width' => 728, 'max_height' => 90, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'between_posts', 'label' => 'Between Posts', 'description' => 'Inserted between posts at a configurable interval', 'position' => 'between_posts', 'is_default' => true, 'is_active' => true, 'sort_order' => 3, 'max_width' => 728, 'max_height' => 250, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'sidebar', 'label' => 'Sidebar', 'description' => 'Sidebar widget area on the discussion list page', 'position' => 'sidebar', 'is_default' => true, 'is_active' => true, 'sort_order' => 4, 'max_width' => 300, 'max_height' => 250, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'above_footer', 'label' => 'Above Footer', 'description' => 'Below the content area, above the footer', 'position' => 'above_footer', 'is_default' => true, 'is_active' => true, 'sort_order' => 5, 'max_width' => 728, 'max_height' => 90, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'footer', 'label' => 'Footer', 'description' => 'At the very bottom of the page', 'position' => 'footer', 'is_default' => true, 'is_active' => true, 'sort_order' => 6, 'max_width' => 728, 'max_height' => 90, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $schema->create('advertisements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('zone_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('name', 150);
            $table->string('type', 20)->default('image');
            $table->text('content')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->text('group_visibility')->nullable();
            $table->unsignedInteger('impressions_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->unsignedInteger('max_impressions')->nullable();
            $table->unsignedInteger('max_clicks')->nullable();
            $table->unsignedInteger('image_changes_count')->default(0);
            $table->unsignedInteger('max_image_changes')->nullable();
            $table->timestamps();

            $table->foreign('zone_id')->references('id')->on('ad_zones')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('is_active');
            $table->index(['zone_id', 'is_active']);
        });

        $schema->create('ad_clicks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ad_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('ad_id')->references('id')->on('advertisements')->onDelete('cascade');
            $table->index('ad_id');
            $table->index('created_at');
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('ad_clicks');
        $schema->dropIfExists('advertisements');
        $schema->dropIfExists('ad_zones');
    },
];
