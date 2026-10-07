<?php

namespace Kirby\Exception {
    class PermissionException extends \Exception
    {
    }
}

namespace {
    require_once __DIR__ . '/../lib/ResizeService.php';
    require_once __DIR__ . '/../lib/ContentCleanupService.php';

    class PanelTestFile
    {
        public array $manipulations = [];

        public function id(): string { return 'image.jpg'; }
        public function template(): string { return 'image'; }
        public function extension(): string { return 'jpg'; }
        public function width(): int { return $this->manipulations ? 800 : 1200; }
        public function height(): int { return 600; }
        public function isResizable(): bool { return true; }
        public function blueprint(): object
        {
            return new class {
                public function create(): array { return ['width' => 800]; }
            };
        }
        public function manipulate(array $create): void { $this->manipulations[] = $create; }
    }

    $file = new PanelTestFile();
    $site = new class($file) {
        public function __construct(private $file) {}
        public function index(bool $all): array { return []; }
        public function files(): array { return [$this->file]; }
    };
    $admin = new class($site) {
        public function __construct(private $site) {}
        public function site() { return $this->site; }
        public function user(): object { return new class { public function isAdmin(): bool { return true; } }; }
        public function models(): array { return []; }
        public function multilang(): bool { return false; }
        public function languages(): array { return []; }
    };
    $guest = new class($site) {
        public function __construct(private $site) {}
        public function site() { return $this->site; }
        public function user() { return null; }
    };

    $area = require __DIR__ . '/../areas/clean-up.php';
    if ($area($admin)['menu'] !== true || $area($guest)['menu'] !== false) {
        throw new \RuntimeException('Clean Up menu visibility is incorrect');
    }
    try {
        $area($guest)['views'][0]['action']();
        throw new \RuntimeException('Guest could open the Panel area');
    } catch (\Kirby\Exception\PermissionException $error) {
    }

    $adminViews = $area($admin)['views'];
    if (count($adminViews) !== 2 || $adminViews[1]['pattern'] !== 'clean-up/content') {
        throw new \RuntimeException('Clean Up tab routes are incorrect');
    }
    if ($adminViews[0]['action']()['props']['active'] !== 'images' || $adminViews[1]['action']()['props']['active'] !== 'content') {
        throw new \RuntimeException('Clean Up tabs do not select the matching view');
    }

    $routes = require __DIR__ . '/../routes/api.php';
    $context = new class($admin) {
        public function __construct(private $kirby) {}
        public function kirby() { return $this->kirby; }
        public function requestQuery(string $key) { return null; }
        public function requestBody(): array { return ['offset' => 0, 'limit' => 1]; }
    };
    $preview = $routes[0]['action']->call($context);
    if (array_column($preview['pending'], 'id') !== ['image.jpg']) {
        throw new \RuntimeException('Admin preview did not return the pending image');
    }
    $contentPreview = $routes[2]['action']->call($context);
    if ($contentPreview !== ['pending' => [], 'errors' => []]) {
        throw new \RuntimeException('Admin content preview did not return a scan result');
    }
    $batch = $routes[1]['action']->call($context);
    if (array_column($batch['processed'], 'id') !== ['image.jpg'] || $batch['nextOffset'] !== null || $file->manipulations !== [['width' => 800]]) {
        throw new \RuntimeException('Admin batch did not process the pending image');
    }
    $contentClean = $routes[3]['action']->call($context);
    if ($contentClean !== ['cleaned' => [], 'errors' => []]) {
        throw new \RuntimeException('Admin content cleanup did not return a result');
    }

    $blocked = new class($guest) {
        public function __construct(private $kirby) {}
        public function kirby() { return $this->kirby; }
        public function requestQuery(string $key) { return null; }
        public function requestBody(): array { return ['offset' => 0, 'limit' => 1]; }
    };
    foreach ($routes as $route) {
        try {
            $route['action']->call($blocked);
            throw new \RuntimeException('Guest could call ' . $route['pattern']);
        } catch (\Kirby\Exception\PermissionException $error) {
        }
    }

    echo "Panel access and API tests passed\n";
}