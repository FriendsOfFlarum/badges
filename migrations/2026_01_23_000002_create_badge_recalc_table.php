<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('fof_badge_recalc', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('started_by_user_id')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->unsignedInteger('total_users')->default(0);
            $table->unsignedInteger('processed_users')->default(0);
            $table->unsignedInteger('total_badges')->default(0);
            $table->unsignedInteger('awarded')->default(0);
            $table->unsignedInteger('revoked')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('total_chunks')->default(0);
            $table->unsignedInteger('processed_chunks')->default(0);
            $table->unsignedInteger('chunk_size')->default(500);
            $table->unsignedInteger('badge_id')->nullable();
            $table->boolean('no_revoke')->default(false);
            $table->boolean('reapply_actions')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('started_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('badge_id')
                ->references('id')
                ->on('fof_badges')
                ->onDelete('cascade');

            $table->index('status');
            $table->index('created_at');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('fof_badge_recalc');
    },
];
