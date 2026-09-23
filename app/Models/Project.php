<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Project extends Model implements AuditableContract
{
    /** @use HasFactory<ProjectFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'repository',
        'branch',
    ];
}
