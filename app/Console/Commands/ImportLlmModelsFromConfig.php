<?php

namespace App\Console\Commands;

use App\Models\LlmModel;
use App\Services\LlmModelCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('llm-models:import-from-config')]
#[Description('Carga inicial do catálogo de modelos de LLM com o snapshot que vivia em config')]
class ImportLlmModelsFromConfig extends Command
{
    /**
     * Snapshot dos modelos que estavam em config/chat.php e dos preços de config/llm.php.
     * Fica no comando para a carga inicial não depender mais dessas chaves.
     *
     * @var list<array{model_id: string, name: string, tier: string, provider: string, price_input?: float, price_cached?: float, price_output?: float}>
     */
    private const LEGACY_MODELS = [
        [
            'model_id' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
            'name' => 'Nemotron 3 Ultra',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ],
        [
            'model_id' => 'poolside/laguna-s-2.1:free',
            'name' => 'Laguna S 2.1',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ],
        [
            'model_id' => 'nvidia/nemotron-3.5-lightning:free',
            'name' => 'Nemotron 3.5 Lightning',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ],
        [
            'model_id' => 'inclusionai/ling-3.0-flash-fin:free',
            'name' => 'Ling 3.0 Flash Fin',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ],
        [
            'model_id' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini',
            'tier' => LlmModel::TIER_PAID,
            'provider' => LlmModel::PROVIDER_OPENAI,
            'price_input' => 0.15,
            'price_cached' => 0.075,
            'price_output' => 0.60,
        ],
        [
            'model_id' => 'gpt-4o',
            'name' => 'GPT-4o',
            'tier' => LlmModel::TIER_PAID,
            'provider' => LlmModel::PROVIDER_OPENAI,
            'price_input' => 2.50,
            'price_cached' => 1.25,
            'price_output' => 10.00,
        ],
        [
            'model_id' => 'gpt-4.1',
            'name' => 'GPT-4.1',
            'tier' => LlmModel::TIER_PAID,
            'provider' => LlmModel::PROVIDER_OPENAI,
            'price_input' => 2.00,
            'price_cached' => 0.50,
            'price_output' => 8.00,
        ],
        [
            'model_id' => 'o4-mini',
            'name' => 'o4 Mini',
            'tier' => LlmModel::TIER_PAID,
            'provider' => LlmModel::PROVIDER_OPENAI,
            'price_input' => 0.55,
            'price_cached' => 0.14,
            'price_output' => 2.20,
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(LlmModelCatalog $catalog): int
    {
        $imported = 0;
        $skipped = 0;

        foreach (self::LEGACY_MODELS as $model) {
            if (LlmModel::query()->where('model_id', $model['model_id'])->exists()) {
                $skipped++;

                continue;
            }

            $catalog->register($model);
            $imported++;
        }

        $this->info("Catálogo de modelos: {$imported} importado(s), {$skipped} já existente(s) ignorado(s).");

        return self::SUCCESS;
    }
}
