<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Trigger\Metric;

use Carbon\Carbon;
use Flarum\User\User;
use FoF\Badges\Trigger\MetricInterface;
use Illuminate\Database\ConnectionInterface;

class FilesUploadedMetric implements MetricInterface
{
    public function __construct(protected ConnectionInterface $db)
    {
    }

    public function getType(): string
    {
        return 'files_uploaded';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.files_uploaded';
    }

    public function getEventTriggers(): array
    {
        // WasSaved event - actor is the user who uploaded the file
        if (class_exists(\FoF\Upload\Events\File\WasSaved::class)) {
            return [
                \FoF\Upload\Events\File\WasSaved::class => fn ($event) => $event->actor,
            ];
        }

        return [];
    }

    public function getValue(User $user, array $config = []): int
    {
        // Query fof_upload_files table directly
        $query = $this->db->table('fof_upload_files')
            ->where('actor_id', $user->id);

        // Apply date range filter if present
        if (isset($config['date_range'])) {
            if (isset($config['date_range']['start']) && $config['date_range']['start']) {
                $query->where('created_at', '>=', Carbon::parse($config['date_range']['start']));
            }

            if (isset($config['date_range']['end']) && $config['date_range']['end']) {
                $query->where('created_at', '<=', Carbon::parse($config['date_range']['end']));
            }
        }

        return $query->count();
    }

    public function isBoolean(): bool
    {
        return false;
    }

    public function getConfigFields(): array
    {
        return [];
    }

    public function getExtensionDependencies(): array
    {
        return ['fof/upload'];
    }
}
