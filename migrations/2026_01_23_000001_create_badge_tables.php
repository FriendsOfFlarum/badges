<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        // Badge categories
        $schema->create('fof_badge_cat', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // Badges
        $schema->create('fof_badges', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('category_id')->nullable();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 100)->default('fas fa-award');
            $table->string('icon_color', 50)->default('#ffffff');
            $table->string('background_color', 50)->default('#667eea');
            $table->json('trigger_config')->nullable();
            $table->json('actions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('earned_count')->default(0);
            $table->unsignedInteger('order')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('category_id')
                ->references('id')
                ->on('fof_badge_cat')
                ->onDelete('set null');

            $table->index(['is_active', 'category_id'], 'fof_badges_active_cat_idx');
        });

        // User-badge pivot
        $schema->create('fof_badge_user', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('badge_id');
            $table->string('granted_by', 20)->default('trigger');
            $table->unsignedInteger('granted_by_user_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->boolean('is_seen')->default(false);
            $table->boolean('show_on_card')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('earned_at')->useCurrent();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('badge_id')
                ->references('id')
                ->on('fof_badges')
                ->onDelete('cascade');

            $table->foreign('granted_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->unique(['user_id', 'badge_id']);
            $table->index('user_id');
            $table->index('earned_at');
            $table->index('granted_by', 'fof_badge_user_granted_idx');
            $table->index(['badge_id', 'user_id'], 'fof_badge_user_badge_user_idx');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('fof_badge_user');
        $schema->dropIfExists('fof_badges');
        $schema->dropIfExists('fof_badge_cat');
    },
];
