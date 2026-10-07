<?php

require_once __DIR__ . '/../lib/OrphanedFileService.php';

use Allsizes\BatchResize\OrphanedFileService;

class OrphanedFileTestVersion
{
    public function __construct(private array $content, private bool $fails = false)
    {
    }

    public function read(string $language): ?array
    {
        if ($this->fails) {
            throw new RuntimeException('Read failed');
        }

        return $this->content[$language] ?? null;
    }
}

class OrphanedFileTestModel
{
    public function __construct(private string $name, private OrphanedFileTestVersion $content)
    {
    }

    public function id(): string { return $this->name; }
    public function version(string $version): OrphanedFileTestVersion { return $this->content; }
}

class OrphanedFileTestFile extends OrphanedFileTestModel
{
    public bool $deleted = false;

    public function __construct(private string $name, private string $fileName, private object $parentModel)
    {
        parent::__construct($name, new OrphanedFileTestVersion(['default' => ['uuid' => 'uuid-' . $name]]));
    }

    public function filename(): string { return $this->fileName; }
    public function parent(): object { return $this->parentModel; }
    public function uuid(): object { return new class($this->fileName) {
        public function __construct(private string $name) {}
        public function id(): string { return 'uuid-' . $this->name; }
        public function toString(): string { return 'file://' . $this->id(); }
    }; }
    public function delete(): bool { $this->deleted = true; return true; }
}

$parent = new class {
    public function id(): string { return 'home'; }
};
$orphan = new OrphanedFileTestFile('orphan.jpg', 'orphan.jpg', $parent);
$uuidReference = new OrphanedFileTestFile('uuid.jpg', 'uuid.jpg', $parent);
$tagReference = new OrphanedFileTestFile('tagged.jpg', 'tagged.jpg', $parent);
$filenameReference = new OrphanedFileTestFile('mentioned.jpg', 'mentioned.jpg', $parent);
$content = new OrphanedFileTestModel('home', new OrphanedFileTestVersion([
    'default' => [
        'cover' => 'file://uuid.jpg',
        'body' => '(image: tagged.jpg)',
        'caption' => 'The original image was named mentioned.jpg',
    ],
]));
$kirby = new class([$orphan, $uuidReference, $tagReference, $filenameReference, $content]) {
    public function __construct(private array $models) {}
    public function models(): array { return $this->models; }
    public function multilang(): bool { return false; }
    public function languages(): array { return []; }
};

$service = new OrphanedFileService($kirby);
$preview = $service->scan();
if (array_column($preview['pending'], 'id') !== ['orphan.jpg'] || $preview['errors'] !== []) {
    throw new RuntimeException('Preview should only list files with no content references.');
}

$result = $service->delete(['orphan.jpg', 'uuid.jpg']);
if (array_column($result['deleted'], 'id') !== ['orphan.jpg'] || !$orphan->deleted) {
    throw new RuntimeException('Delete should remove confirmed orphan candidates.');
}
if ($result['skipped'] !== [['id' => 'uuid.jpg']] || $uuidReference->deleted) {
    throw new RuntimeException('Delete should re-check references before removing files.');
}

$siteParent = new class {
    public function id(): string { return ''; }
};
$siteFile = new OrphanedFileTestFile('site.jpg', 'site.jpg', $siteParent);
$siteKirby = new class([$siteFile]) {
    public function __construct(private array $models) {}
    public function models(): array { return $this->models; }
    public function multilang(): bool { return false; }
    public function languages(): array { return []; }
};
if ((new OrphanedFileService($siteKirby))->scan()['pending'][0]['location'] !== 'Site') {
    throw new RuntimeException('Site-level files should show their location.');
}

$blockedFile = new OrphanedFileTestFile('blocked.jpg', 'blocked.jpg', $parent);
$brokenContent = new OrphanedFileTestModel('broken', new OrphanedFileTestVersion(['default' => []], true));
$brokenKirby = new class([$blockedFile, $brokenContent]) {
    public function __construct(private array $models) {}
    public function models(): array { return $this->models; }
    public function multilang(): bool { return false; }
    public function languages(): array { return []; }
};
$blockedResult = (new OrphanedFileService($brokenKirby))->delete(['blocked.jpg']);
if ($blockedResult['deleted'] !== [] || count($blockedResult['errors']) !== 1 || $blockedFile->deleted) {
    throw new RuntimeException('Deletion should stop when content cannot be scanned.');
}

echo "Orphaned file tests passed\n";