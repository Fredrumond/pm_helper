<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class PromptModel extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'prompt',
        'model',
    ];
}
