<?php

namespace Allsizes\BatchResize;

class OrphanedFileService
{
    private $kirby;

    public function __construct($kirby)
    {
        $this->kirby = $kirby;
    }

    public function scan(): array
    {
        [$files, $errors] = $this->collect();

        return [
            'pending' => array_map([$this, 'publicFile'], array_values(array_filter(
                $files,
                static fn ($file) => $file['referenced'] === false
            ))),
            'errors' => $errors,
        ];
    }

    public function delete(array $ids): array
    {
        [$files, $errors] = $this->collect();
        if ($errors !== []) {
            return ['deleted' => [], 'skipped' => [], 'errors' => $errors];
        }

        $byId = [];
        foreach ($files as $file) {
            $byId[$file['id']] = $file;
        }

        $deleted = [];
        $skipped = [];
        foreach (array_unique($ids) as $id) {
            if (!is_string($id) || !isset($byId[$id]) || $byId[$id]['referenced']) {
                $skipped[] = ['id' => is_string($id) ? $id : ''];
                continue;
            }

            try {
                if ($byId[$id]['file']->delete() !== true) {
                    throw new \RuntimeException('Kirby could not delete the file.');
                }
                $deleted[] = $this->publicFile($byId[$id]);
            } catch (\Throwable $error) {
                $errors[] = ['id' => $id, 'error' => $error->getMessage()];
            }
        }

        return ['deleted' => $deleted, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function collect(): array
    {
        $files = [];
        foreach ($this->kirby->models() as $model) {
            if (!method_exists($model, 'filename') || !method_exists($model, 'delete')) {
                continue;
            }

            $identifiers = [$model->id(), $model->filename()];
            try {
                if (method_exists($model, 'uuid') && ($uuid = $model->uuid()) !== null) {
                    $identifiers[] = $uuid->id();
                    $identifiers[] = $uuid->toString();
                }
            } catch (\Throwable) {
            }

            $parent = method_exists($model, 'parent') ? $model->parent() : null;
            $location = is_object($parent) && method_exists($parent, 'id') ? trim((string)$parent->id()) : '';
            $files[$model->id()] = [
                'id' => $model->id(),
                'filename' => $model->filename(),
                'location' => $location !== '' ? $location : 'Site',
                'identifiers' => array_values(array_unique(array_filter($identifiers))),
                'file' => $model,
                'referenced' => false,
            ];
        }

        $texts = [];
        $errors = [];
        $languages = ['default'];
        if ($this->kirby->multilang() === true) {
            $languages = [];
            foreach ($this->kirby->languages() as $language) {
                $languages[] = $language->code();
            }
        }

        foreach ($this->kirby->models() as $model) {
            if (!method_exists($model, 'version')) {
                continue;
            }

            foreach ($languages as $language) {
                try {
                    $content = $model->version('latest')->read($language);
                    if ($content !== null) {
                        $this->collectText($content, $texts);
                    }
                } catch (\Throwable $error) {
                    $errors[] = $this->errorEntry($model, $language, $error);
                }
            }
        }

        foreach ($files as &$file) {
            $file['referenced'] = $this->isReferenced($file, $texts);
        }
        unset($file);

        return [$files, $errors];
    }

    private function collectText(mixed $value, array &$texts): void
    {
        if (is_string($value)) {
            $texts[] = $value;
            return;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (strtolower((string)$key) === 'uuid') {
                    continue;
                }
                $this->collectText($item, $texts);
            }
        }
    }

    private function isReferenced(array $file, array $texts): bool
    {
        foreach ($texts as $text) {
            foreach ($file['identifiers'] as $identifier) {
                if ($identifier !== '' && stripos($text, $identifier) !== false) {
                    return true;
                }
            }

            if ($file['filename'] !== '' && stripos($text, $file['filename']) !== false) {
                return true;
            }
        }

        return false;
    }

    private function publicFile(array $file): array
    {
        return [
            'id' => $file['id'],
            'filename' => $file['filename'],
            'location' => $file['location'],
        ];
    }

    private function errorEntry($model, string $language, \Throwable $error): array
    {
        return [
            'id' => method_exists($model, 'id') ? $model->id() : get_class($model),
            'language' => $language,
            'error' => $error->getMessage(),
        ];
    }
}