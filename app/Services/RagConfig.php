<?php

namespace App\Services;

use App\Models\LlmModel;
use App\Models\RagSetting;

class RagConfig
{
    public const DEFAULT_CHUNK_SIZE = 500;

    public const DEFAULT_EMBEDDING_MODEL = 'text-embedding-3-small';

    public const DEFAULT_TOP_K = 3;

    public const STRATEGY_SIZE = 'size';

    public const STRATEGY_PARAGRAPH = 'paragraph';

    public const STRATEGY_MARKDOWN = 'markdown';

    public const STRATEGIES = [
        self::STRATEGY_SIZE,
        self::STRATEGY_PARAGRAPH,
        self::STRATEGY_MARKDOWN,
    ];

    public const DEFAULT_STRATEGY = self::STRATEGY_SIZE;

    public const DEFAULT_MIN_SCORE = 0.0;

    public const SYNC_SOURCE_README = 'readme';

    public const SYNC_SOURCE_DOCS_ROOT = 'docs_root';

    public const SYNC_SOURCE_DOCS_ADR = 'docs_adr';

    public const SYNC_SOURCE_DOCS_DISCOVERY = 'docs_discovery';

    public const SYNC_SOURCE_DOCS_REFERENCES = 'docs_references';

    public const SYNC_SOURCE_DOCS_RULES = 'docs_rules';

    public const SYNC_SCOPE_FILE = 'file';

    public const SYNC_SCOPE_RECURSIVE = 'recursive';

    public const SYNC_SCOPE_DOCS_ROOT = 'docs_root';

    /** @var list<string> */
    public const SYNC_SOURCE_KEYS = [
        self::SYNC_SOURCE_README,
        self::SYNC_SOURCE_DOCS_ROOT,
        self::SYNC_SOURCE_DOCS_ADR,
        self::SYNC_SOURCE_DOCS_DISCOVERY,
        self::SYNC_SOURCE_DOCS_REFERENCES,
        self::SYNC_SOURCE_DOCS_RULES,
    ];

    /**
     * @return array<string, array{label: string, description: string, path: string, scope: string}>
     */
    public static function syncSourceCatalog(): array
    {
        $docs = trim((string) config('mcp.github.docs_path', 'docs'), '/');
        $docs = $docs !== '' ? $docs : 'docs';

        return [
            self::SYNC_SOURCE_README => [
                'label' => 'README.md',
                'description' => 'Arquivo de apresentação do projeto na raiz',
                'path' => 'README.md',
                'scope' => self::SYNC_SCOPE_FILE,
            ],
            self::SYNC_SOURCE_DOCS_ROOT => [
                'label' => $docs.'/',
                'description' => 'Arquivos Markdown diretamente na pasta docs, sem subpastas listadas abaixo',
                'path' => $docs,
                'scope' => self::SYNC_SCOPE_DOCS_ROOT,
            ],
            self::SYNC_SOURCE_DOCS_ADR => [
                'label' => $docs.'/adr/',
                'description' => 'Decisões de arquitetura',
                'path' => $docs.'/adr',
                'scope' => self::SYNC_SCOPE_RECURSIVE,
            ],
            self::SYNC_SOURCE_DOCS_DISCOVERY => [
                'label' => $docs.'/discovery/',
                'description' => 'Documentos de Discovery',
                'path' => $docs.'/discovery',
                'scope' => self::SYNC_SCOPE_RECURSIVE,
            ],
            self::SYNC_SOURCE_DOCS_REFERENCES => [
                'label' => $docs.'/references/',
                'description' => 'Referências técnicas',
                'path' => $docs.'/references',
                'scope' => self::SYNC_SCOPE_RECURSIVE,
            ],
            self::SYNC_SOURCE_DOCS_RULES => [
                'label' => $docs.'/rules/',
                'description' => 'Regras do projeto',
                'path' => $docs.'/rules',
                'scope' => self::SYNC_SCOPE_RECURSIVE,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function enabledSyncSourceKeys(): array
    {
        $stored = RagSetting::query()->value('sync_sources');

        if (! is_array($stored)) {
            return self::SYNC_SOURCE_KEYS;
        }

        $keys = array_values(array_filter(
            $stored,
            fn (mixed $key): bool => is_string($key) && in_array($key, self::SYNC_SOURCE_KEYS, true),
        ));

        if ($keys === []) {
            return self::SYNC_SOURCE_KEYS;
        }

        return $keys;
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{path: string, scope: string}>
     */
    public function indexableCollectionSpecs(array $keys): array
    {
        $keys = array_values(array_filter(
            $keys,
            fn (string $key): bool => in_array($key, self::SYNC_SOURCE_KEYS, true),
        ));

        if ($keys === [] || count($keys) === count(self::SYNC_SOURCE_KEYS)) {
            return $this->legacyIndexableCollectionSpecs();
        }

        $catalog = self::syncSourceCatalog();
        $specs = [];

        foreach (self::SYNC_SOURCE_KEYS as $key) {
            if (! in_array($key, $keys, true)) {
                continue;
            }

            $entry = $catalog[$key];
            $specs[] = [
                'path' => $entry['path'],
                'scope' => $entry['scope'],
            ];
        }

        return $specs;
    }

    /**
     * @return list<array{path: string, scope: string}>
     */
    public function indexableCollectionSpecsForSync(): array
    {
        return $this->indexableCollectionSpecs($this->enabledSyncSourceKeys());
    }

    /**
     * @param  list<string>  $keys
     */
    public function normalizeSyncSourceKeys(array $keys): array
    {
        return array_values(array_unique(array_filter(
            $keys,
            fn (mixed $key): bool => is_string($key) && in_array($key, self::SYNC_SOURCE_KEYS, true),
        )));
    }

    public function chunkSize(): int
    {
        $stored = RagSetting::query()->value('chunk_size');

        if ($stored === null || $stored === '' || ! is_numeric($stored)) {
            return self::DEFAULT_CHUNK_SIZE;
        }

        return (int) $stored;
    }

    public function embeddingModel(): string
    {
        $stored = RagSetting::query()->value('embedding_model');

        if (is_string($stored) && $stored !== '' && $this->modelIsInCatalog($stored)) {
            return $stored;
        }

        return self::DEFAULT_EMBEDDING_MODEL;
    }

    public function topK(): int
    {
        $stored = RagSetting::query()->value('top_k');

        if ($stored === null || $stored === '' || ! is_numeric($stored)) {
            return self::DEFAULT_TOP_K;
        }

        return (int) $stored;
    }

    public function strategy(): string
    {
        $stored = RagSetting::query()->value('chunk_strategy');

        if (! is_string($stored) || ! in_array($stored, self::STRATEGIES, true)) {
            return self::DEFAULT_STRATEGY;
        }

        return $stored;
    }

    public function minScore(): float
    {
        $stored = RagSetting::query()->value('min_score');

        if ($stored === null || $stored === '' || ! is_numeric($stored)) {
            return self::DEFAULT_MIN_SCORE;
        }

        $score = (float) $stored;

        if ($score < 0 || $score > 1) {
            return self::DEFAULT_MIN_SCORE;
        }

        return $score;
    }

    public function save(
        int $chunkSize,
        string $embeddingModel,
        int $topK,
        string $strategy = self::DEFAULT_STRATEGY,
        float $minScore = self::DEFAULT_MIN_SCORE,
        ?array $syncSourceKeys = null,
    ): bool {
        if (! $this->modelIsInCatalog($embeddingModel)) {
            return false;
        }

        if (! in_array($strategy, self::STRATEGIES, true) || ! is_finite($minScore) || $minScore < 0 || $minScore > 1) {
            return false;
        }

        if ($syncSourceKeys !== null) {
            foreach ($syncSourceKeys as $key) {
                if (! is_string($key) || ! in_array($key, self::SYNC_SOURCE_KEYS, true)) {
                    return false;
                }
            }
            $syncSourceKeys = $this->normalizeSyncSourceKeys($syncSourceKeys);
        }

        $setting = RagSetting::query()->first() ?? new RagSetting;
        $payload = [
            'chunk_size' => $chunkSize,
            'embedding_model' => $embeddingModel,
            'top_k' => $topK,
            'chunk_strategy' => $strategy,
            'min_score' => $minScore,
        ];

        if ($syncSourceKeys !== null) {
            $payload['sync_sources'] = $syncSourceKeys;
        }

        $setting->fill($payload);
        $setting->save();

        return true;
    }

    /**
     * @return list<array{path: string, scope: string}>
     */
    private function legacyIndexableCollectionSpecs(): array
    {
        $docs = trim((string) config('mcp.github.docs_path', 'docs'), '/');
        $docs = $docs !== '' ? $docs : 'docs';

        return [
            ['path' => 'README.md', 'scope' => self::SYNC_SCOPE_FILE],
            ['path' => $docs, 'scope' => self::SYNC_SCOPE_RECURSIVE],
        ];
    }

    private function modelIsInCatalog(string $model): bool
    {
        return LlmModel::query()->where('model_id', $model)->exists();
    }
}
