<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Tests\integration\api;

use Composer\InstalledVersions;
use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Flarum\Extension\ExtensionManager;
use Flarum\Locale\LocaleManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\TestCase;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Translation\Loader\ArrayLoader;

class InstallDefaultBadgesLocaleTest extends TestCase
{
    protected function allowedRepeatedQueries(): array
    {
        // Installation intentionally saves the fixed ten default badge models;
        // these are writes, not repeated relationship reads on a growing list.
        return [\Flarum\Testing\integration\RepeatedQueryDetector::normalise(
            'insert into '.$this->database()->getQueryGrammar()->wrapTable('fof_badges')
        )];
    }

    public static function turkishTranslations(): array
    {
        return [
            'no Turkish default badge keys' => [[]],
            'two Turkish fields, remaining fields fall back' => [[
                'fof-badges.defaults.categories.getting-started.name' => 'Başlangıç',
                'fof-badges.defaults.badges.first-post.description' => 'İlk yanıtını yaz',
            ]],
        ];
    }

    #[DataProvider('turkishTranslations')]
    public function test_turkish_only_install_without_english_language_extension(array $translations): void
    {
        $this->extension('fof-badges');
        $this->setting('default_locale', 'tr');
        // Separate the cached catalogues of the empty and partial fixtures using
        // core's real revision stamp, without replacing its translator/provider.
        $this->setting(LocaleManager::REVISION_STAMP_KEY, hash('sha256', serialize($translations)));
        $this->extend(new class($translations) implements ExtenderInterface {
            public function __construct(private array $translations)
            {
            }

            public function extend(Container $container, ?Extension $extension = null): void
            {
                // flarum/testing enables the extension before applying settings;
                // Locales::onEnable() already resolved the default-en manager.
                // Discard those early instances so the real core provider's
                // unchanged factories now read default_locale=tr. Its registered
                // resolving callbacks (including badges' Locales) remain intact.
                $container->forgetInstance(LocaleManager::class);
                $container->forgetInstance('translator');
                $container->resolving(LocaleManager::class, function (LocaleManager $locales) {
                    $locales->addLocale('tr', 'Türkçe (test)');
                    $translator = $locales->getTranslator();
                    $translator->addLoader('array', new ArrayLoader());
                    // Synthetic Turkish pack; English is loaded exclusively by
                    // core and fof-badges' own Extend\Locales in extend.php.
                    $translator->addResource('array', $this->translations + [
                        'core.forum.index.page_title' => 'Tüm Tartışmalar',
                    ], 'tr', 'messages+intl-icu');
                });
            }
        });

        $container = $this->app()->getContainer();
        $settings = $container->make(SettingsRepositoryInterface::class);
        $this->assertSame('tr', $settings->get('default_locale'));
        $this->assertSame(['fof-badges'], $container->make(ExtensionManager::class)->getEnabled());
        $this->assertFalse(InstalledVersions::isInstalled('flarum/lang-english'));

        $locales = $container->make(LocaleManager::class);
        $translator = $locales->getTranslator();
        $this->assertSame(['tr'], array_keys($locales->getLocales()));
        $this->assertFalse($locales->hasLocale('en'));
        $this->assertSame('tr', $translator->getLocale());
        $this->assertSame(['en'], $translator->getFallbackLocales());

        // Independent English expectations: every persisted name/description,
        // not expectations obtained by translating with the service under test.
        $expected = [
            'fof_badge_cat' => [
                'getting-started' => ['Getting Started', 'Badges for new members taking their first steps'],
                'milestones' => ['Milestones', 'Badges for reaching important milestones'],
                'posting' => ['Posting', 'Badges for posting milestones and contributions'],
            ],
            'fof_badges' => [
                'first-post' => ['First Post', 'Write your first reply'],
                'conversation-starter' => ['Conversation Starter', 'Start your first discussion'],
                'profile-picture' => ['Profile Picture', 'Upload a profile picture'],
                'first-week' => ['First Week', 'Be a member for 7 days'],
                'regular' => ['Regular', 'Be a member for 30 days'],
                'anniversary' => ['Anniversary', 'Be a member for one year'],
                'active-member' => ['Active Member', 'Post 50 replies'],
                'prolific' => ['Prolific', 'Post 200 replies'],
                'topic-starter' => ['Topic Starter', 'Start 10 discussions'],
                'editor' => ['Editor', 'Edit 10 posts to improve them'],
            ],
        ];
        foreach ($expected as $table => $records) {
            $this->assertSame(0, $this->database()->table($table)->count());
        }

        $body = $this->installDefaults();
        $this->assertCount(10, $body['data']);
        $this->assertSame('tr', $translator->getLocale());
        $english = $translator->getCatalogue('en');
        $turkish = $translator->getCatalogue('tr');
        $fieldCount = 0;
        foreach ($expected as $table => $records) {
            $type = $table === 'fof_badges' ? 'badges' : 'categories';
            $rows = $this->database()->table($table)->get()->keyBy('slug');
            $this->assertCount(count($records), $rows);
            foreach ($records as $slug => $fields) {
                $this->assertTrue($rows->has($slug));
                foreach (['name', 'description'] as $index => $field) {
                    $key = "fof-badges.defaults.$type.$slug.$field";
                    $this->assertTrue($english->defines($key, 'messages+intl-icu'), $key);
                    $this->assertSame($fields[$index], $english->get($key, 'messages+intl-icu'));
                    // defines() excludes fallback messages: prove tr itself has
                    // no badge defaults except the two explicit partial fields.
                    $this->assertSame(isset($translations[$key]), $turkish->defines($key, 'messages+intl-icu'), $key);
                    $this->assertSame($translations[$key] ?? $fields[$index], $rows[$slug]->$field, $key);
                    $this->assertStringNotContainsString('fof-badges.', $rows[$slug]->$field);
                    $fieldCount++;
                }
            }
        }
        $this->assertSame(26, $fieldCount);
        foreach ($body['data'] as $badge) {
            $this->assertSame(
                $this->database()->table('fof_badges')->where('id', $badge['id'])->value('name'),
                $badge['name']
            );
        }

        // An existing administrator-edited record and every other column must
        // survive a second real API call, even if translations have changed.
        $this->database()->table('fof_badges')->where('slug', 'first-post')->update(['name' => 'Özel rozet']);
        $before = $this->snapshot();
        $translator->addResource('array', [
            'fof-badges.defaults.badges.first-post.name' => 'Yeni çeviri',
        ], 'tr', 'messages+intl-icu');
        $this->assertSame(['data' => []], $this->installDefaults());
        $this->assertSame($before, $this->snapshot());
        $this->assertFalse($locales->hasLocale('en'));
    }

    private function installDefaults(): array
    {
        $response = $this->send($this->request('POST', '/api/badges/install-defaults', [
            'authenticatedAs' => 1,
        ]));
        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), $body);

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    private function snapshot(): array
    {
        return array_map(
            fn (string $table) => $this->database()->table($table)->orderBy('id')->get()
                ->map(fn (object $row) => (array) $row)->all(),
            ['fof_badge_cat', 'fof_badges']
        );
    }
}
