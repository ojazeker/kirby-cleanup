<?php

use Allsizes\BatchResize\ResizeService;
use Allsizes\BatchResize\ContentCleanupService;
use Allsizes\BatchResize\OrphanedFileService;
use Kirby\Exception\PermissionException;

$authorize = static function ($context): void {
    if ($context->kirby()->user()?->isAdmin() !== true) {
        throw new PermissionException('Only administrators can use the Clean Up tool.');
    }
};

$serviceFor = static function ($context, $forceName) use ($authorize): ResizeService {
    $authorize($context);
    if ($forceName !== null && $forceName !== '' && (!is_string($forceName) || !preg_match('/^[a-z0-9_-]+$/i', $forceName))) {
        throw new InvalidArgumentException('Invalid file blueprint name.');
    }

    set_time_limit(0);
    ini_set('memory_limit', '512M');

    return new ResizeService($context->kirby(), $forceName ?: null);
};

$contentServiceFor = static function ($context) use ($authorize): ContentCleanupService {
    $authorize($context);
    set_time_limit(0);
    ini_set('memory_limit', '512M');

    return new ContentCleanupService($context->kirby());
};

$orphanedFileServiceFor = static function ($context) use ($authorize): OrphanedFileService {
    $authorize($context);
    set_time_limit(0);
    ini_set('memory_limit', '512M');

    return new OrphanedFileService($context->kirby());
};

return [
    [
        'pattern' => 'clean-up/preview',
        'method' => 'GET',
        'action' => function () use ($serviceFor) {
            $service = $serviceFor($this, $this->requestQuery('force'));
            return $service->scan($service->files());
        },
    ],
    [
        'pattern' => 'clean-up/batch',
        'method' => 'POST',
        'action' => function () use ($serviceFor) {
            $body = $this->requestBody();
            if (!is_array($body)) {
                throw new InvalidArgumentException('Invalid batch request.');
            }
            $service = $serviceFor($this, $body['force'] ?? null);
            $offset = max(0, (int)($body['offset'] ?? 0));
            $limit = max(1, min(100, (int)($body['limit'] ?? 10)));
            return $service->process($service->files(), $offset, $limit);
        },
    ],
    [
        'pattern' => 'clean-up/content-preview',
        'method' => 'GET',
        'action' => function () use ($contentServiceFor) {
            return $contentServiceFor($this)->scan($this->requestQuery('ignore'));
        },
    ],
    [
        'pattern' => 'clean-up/content-clean',
        'method' => 'POST',
        'action' => function () use ($contentServiceFor) {
            $body = $this->requestBody();
            if (!is_array($body)) {
                throw new InvalidArgumentException('Invalid content cleanup request.');
            }

            return $contentServiceFor($this)->clean($body['ignore'] ?? null);
        },
    ],
    [
        'pattern' => 'clean-up/orphaned-files-preview',
        'method' => 'GET',
        'action' => function () use ($orphanedFileServiceFor) {
            return $orphanedFileServiceFor($this)->scan();
        },
    ],
    [
        'pattern' => 'clean-up/orphaned-files-delete',
        'method' => 'POST',
        'action' => function () use ($orphanedFileServiceFor) {
            $service = $orphanedFileServiceFor($this);
            $body = $this->requestBody();
            if (!is_array($body) || !isset($body['ids']) || !is_array($body['ids'])) {
                throw new InvalidArgumentException('Invalid orphaned file request.');
            }

            return $service->delete($body['ids']);
        },
    ],
];