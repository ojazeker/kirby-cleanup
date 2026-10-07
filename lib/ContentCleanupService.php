<?php

namespace Allsizes\BatchResize;

class ContentCleanupService
{
    private $kirby;

    private const DEFAULT_IGNORE = ['uuid', 'title', 'slug', 'template', 'sort', 'focus'];

    public function __construct($kirby)
    {
        $this->kirby = $kirby;
    }

    public function scan($ignore = null): array
    {
        [$entries, $errors] = $this->collect($ignore);

        return [
            'pending' => array_map([$this, 'publicEntry'], $entries),
            'errors' => $errors,
        ];
    }

    public function clean($ignore = null): array
    {
        [$entries, $errors] = $this->collect($ignore);
        $cleaned = [];

        foreach ($entries as $entry) {
            try {
                $data = array_diff_key($entry['content'], array_flip($entry['fields']));
                $entry['version']->replace($data, $entry['language']);
                $cleaned[] = $this->publicEntry($entry);
            } catch (\Throwable $error) {
                $errors[] = $this->errorEntry($entry['model'], $entry['language'], $error);
            }
        }

        return ['cleaned' => $cleaned, 'errors' => $errors];
    }

    private function collect($ignore): array
    {
        $ignore = $this->normalizeIgnore($ignore);
        $entries = [];
        $errors = [];

        foreach ($this->kirby->models() as $model) {
            $languages = ['default'];
            if ($this->kirby->multilang() === true) {
                $languages = [];
                foreach ($this->kirby->languages() as $language) {
                    $languages[] = $language->code();
                }
            }

            foreach ($languages as $language) {
                try {
                    $version = $model->version('latest');
                    $content = $version->read($language);
                    if ($content === null) {
                        continue;
                    }

                    $blueprintFields = array_keys($model->blueprint()->fields());
                    $fields = array_values(array_diff(array_keys($content), $blueprintFields, $ignore));
                    if ($fields === []) {
                        continue;
                    }

                    $entries[] = [
                        'model' => $model,
                        'version' => $version,
                        'content' => $content,
                        'id' => $model->id(),
                        'type' => (new \ReflectionClass($model))->getShortName(),
                        'language' => $language,
                        'fields' => $fields,
                    ];
                } catch (\Throwable $error) {
                    $errors[] = $this->errorEntry($model, $language, $error);
                }
            }
        }

        return [$entries, $errors];
    }

    private function normalizeIgnore($ignore): array
    {
        if ($ignore === null) {
            $ignore = self::DEFAULT_IGNORE;
        } elseif (is_string($ignore)) {
            $ignore = $ignore === '' ? [] : explode(',', $ignore);
        }

        if (!is_array($ignore)) {
            throw new \InvalidArgumentException('Ignored fields must be a list or comma-separated string.');
        }

        return array_values(array_filter(array_map(
            static fn ($field) => strtolower(trim((string)$field)),
            $ignore
        ), static fn ($field) => $field !== ''));
    }

    private function publicEntry(array $entry): array
    {
        return [
            'id' => $entry['id'],
            'model' => $entry['type'],
            'language' => $entry['language'],
            'fields' => implode(', ', $entry['fields']),
        ];
    }

    private function errorEntry($model, string $language, \Throwable $error): array
    {
        return [
            'id' => method_exists($model, 'id') ? $model->id() : get_class($model),
            'model' => (new \ReflectionClass($model))->getShortName(),
            'language' => $language,
            'error' => $error->getMessage(),
        ];
    }
}