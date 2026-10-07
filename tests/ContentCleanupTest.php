<?php

require_once __DIR__ . '/../lib/ContentCleanupService.php';

use Allsizes\BatchResize\ContentCleanupService;

class ContentCleanupTestVersion
{
    public function __construct(public array $content, public bool $fails = false)
    {
    }

    public function read(string $language): ?array
    {
        return $this->content[$language] ?? null;
    }

    public function replace(array $content, string $language): void
    {
        if ($this->fails) {
            throw new RuntimeException('Write failed');
        }

        $this->content[$language] = $content;
    }
}

class ContentCleanupTestBlueprint
{
    public function fields(): array
    {
        return ['title' => [], 'slug' => [], 'email' => []];
    }
}

class ContentCleanupTestModel
{
    public function __construct(private string $name, private ContentCleanupTestVersion $content)
    {
    }

    public function id(): string
    {
        return $this->name;
    }

    public function version(string $version): ContentCleanupTestVersion
    {
        return $this->content;
    }

    public function blueprint(): ContentCleanupTestBlueprint
    {
        return new ContentCleanupTestBlueprint();
    }
}

class ContentCleanupTestLanguage
{
    public function __construct(private string $name)
    {
    }

    public function code(): string
    {
        return $this->name;
    }
}

$pageVersion = new ContentCleanupTestVersion([
    'en' => ['title' => 'Page', 'uuid' => 'abc', 'legacy' => 'remove'],
    'nl' => ['title' => 'Pagina', 'slug' => 'pagina', 'legacy' => 'verwijder'],
]);
$failedVersion = new ContentCleanupTestVersion([
    'en' => ['email' => 'editor@example.com', 'legacy' => 'remove'],
    'nl' => ['email' => 'editor@example.com'],
], true);
$models = [
    new ContentCleanupTestModel('home', $pageVersion),
    new ContentCleanupTestModel('editor', $failedVersion),
];
$kirby = new class($models) {
    public function __construct(private array $models)
    {
    }

    public function models(): array
    {
        return $this->models;
    }

    public function multilang(): bool
    {
        return true;
    }

    public function languages(): ArrayIterator
    {
        return new ArrayIterator([new ContentCleanupTestLanguage('en'), new ContentCleanupTestLanguage('nl')]);
    }
};

$service = new ContentCleanupService($kirby);
$preview = $service->scan();
if (array_column($preview['pending'], 'language') !== ['en', 'nl', 'en'] || count($preview['errors']) !== 0) {
    throw new RuntimeException('Preview should find undefined fields in each language.');
}
if ($preview['pending'][0]['fields'] !== 'legacy' || !str_contains($preview['pending'][1]['fields'], 'legacy')) {
    throw new RuntimeException('Preview should list only fields missing from the blueprint and ignore defaults.');
}

$result = $service->clean();
if (count($result['cleaned']) !== 2 || count($result['errors']) !== 1) {
    throw new RuntimeException('Cleanup should report successful writes and isolated failures.');
}
if (isset($pageVersion->content['en']['legacy']) || $pageVersion->content['en']['uuid'] !== 'abc') {
    throw new RuntimeException('Cleanup should remove undefined fields while preserving ignored fields.');
}
if ($result['errors'][0]['id'] !== 'editor' || $result['errors'][0]['error'] !== 'Write failed') {
    throw new RuntimeException('Cleanup should identify content write failures.');
}

echo "Content cleanup tests passed\n";