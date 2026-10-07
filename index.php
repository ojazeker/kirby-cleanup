<?php

require_once __DIR__ . '/lib/ResizeService.php';
require_once __DIR__ . '/lib/ContentCleanupService.php';

Kirby::plugin('allsizes/batch-resize', [
    'areas' => [
        'clean-up' => require __DIR__ . '/areas/clean-up.php',
    ],
    'api' => [
        'routes' => require __DIR__ . '/routes/api.php',
    ],
]);