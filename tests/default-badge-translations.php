<?php

// Run directly with PHP. In-memory model doubles deliberately prevent database access.
namespace FoF\Badges {
    class BadgeCategory extends \stdClass
    {
        public static array $saved = [];

        public static function build($name, $slug, $description, $categoryId = null): static
        {
            $record = new static;
            $record->name = $name;
            $record->slug = $slug;
            $record->description = $description;
            $record->category_id = $categoryId;

            return $record;
        }

        public function save(): void
        {
            $this->id = count(static::$saved) + 1;
            static::$saved[$this->slug] = (array) $this;
        }
    }

    class Badge extends BadgeCategory
    {
        public static array $saved = [];
    }
}

namespace {
    require __DIR__.'/bootstrap.php';

    function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new \RuntimeException($message);
        }
    }

    $translator = new \Flarum\Locale\Translator('en');
    $translator->setFallbackLocales(['en']);
    $translator->addLoader('prefixed_yaml', new \Flarum\Locale\PrefixedYamlFileLoader());
    $translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
    $locales = new \Flarum\Locale\LocaleManager($translator);
    $locales->addTranslations('en', dirname(__DIR__).'/locale/en.yml');
    $controller = new \FoF\Badges\Api\Controller\InstallDefaultBadgesController($translator);
    $categories = new \ReflectionMethod($controller, 'createCategories');
    $badges = new \ReflectionMethod($controller, 'createBadges');
    $categories->setAccessible(true);
    $badges->setAccessible(true);
    $install = function () use ($controller, $categories, $badges): array {
        \FoF\Badges\BadgeCategory::$saved = [];
        \FoF\Badges\Badge::$saved = [];
        $now = \Carbon\Carbon::now();
        $ids = $categories->invoke($controller, $now);
        $badges->invoke($controller, $ids, $now);

        return ['categories' => \FoF\Badges\BadgeCategory::$saved, 'badges' => \FoF\Badges\Badge::$saved];
    };
    $english = $install();
    check(count($english['categories']) === 3 && count($english['badges']) === 10, 'Default counts');
    $messages = [];
    foreach ($english as $type => $records) {
        foreach ($records as $slug => $record) {
            foreach (['name', 'description'] as $field) {
                check(! str_starts_with($record[$field], 'fof-badges.'), 'Missing English key');
                $messages["fof-badges.defaults.$type.$slug.$field"] = "Translated $slug $field";
            }
        }
    }
    // A partially translated language must fall back per field, not per record.
    unset($messages['fof-badges.defaults.badges.first-post.description']);
    $translator->addResource('array', $messages, 'tr', 'messages+intl-icu');
    $locales->addLocale('tr', 'Turkish');
    \Flarum\User\User::registerPreference('locale');
    $actor = new \Flarum\User\User;
    $actor->exists = true;
    $actor->setPreference('locale', 'tr');
    $request = \Flarum\Http\RequestUtil::withActor(new \Laminas\Diactoros\ServerRequest(), $actor);
    $handler = new class implements \Psr\Http\Server\RequestHandlerInterface {
        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            check($request->getAttribute('locale') === 'tr', 'Actor locale selected by core');

            return new \Laminas\Diactoros\Response\EmptyResponse();
        }
    };
    (new \Flarum\Http\Middleware\SetLocale($locales))->process($request, $handler);
    $translated = $install();
    foreach ($english as $type => $records) {
        foreach ($records as $slug => $record) {
            foreach (['name', 'description'] as $field) {
                $record[$field] = $messages["fof-badges.defaults.$type.$slug.$field"] ?? $record[$field];
            }
            check($translated[$type][$slug] === $record, "Translated text and preserved config: $slug");
        }
    }
    $translator->setLocale('de');
    check($install() === $english, 'Entirely untranslated locale falls back to English');
    print "Default badge translation checks passed (actor locale, 26 fields, partial/full fallback, configuration).\n";
}
