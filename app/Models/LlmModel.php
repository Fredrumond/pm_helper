<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class LlmModel extends Model implements AuditableContract
{
    use Auditable;

    public const TIER_FREE = 'free';

    public const TIER_PAID = 'paid';

    /**
     * @var list<string>
     */
    public const TIERS = [
        self::TIER_FREE,
        self::TIER_PAID,
    ];

    public const PROVIDER_OPENROUTER = 'openrouter';

    public const PROVIDER_OPENAI = 'openai';

    /**
     * @var list<string>
     */
    public const PROVIDERS = [
        self::PROVIDER_OPENROUTER,
        self::PROVIDER_OPENAI,
    ];

    protected $fillable = [
        'model_id',
        'name',
        'tier',
        'provider',
        'active',
        'price_input',
        'price_cached',
        'price_output',
    ];

    protected $casts = [
        'active' => 'boolean',
        'price_input' => 'decimal:4',
        'price_cached' => 'decimal:4',
        'price_output' => 'decimal:4',
    ];

    public function isPaid(): bool
    {
        return $this->tier === self::TIER_PAID;
    }
}
