<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestModel extends Model
{
    protected $fillable = [
        'name',
        'email',
        'age',
        'is_active',
        'created_at',
    ];

    protected $casts = [
        'age' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(RelatedModel::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProfileModel::class);
    }
}
