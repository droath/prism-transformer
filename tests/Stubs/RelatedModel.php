<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

class RelatedModel extends Model
{
    protected $fillable = [
        'title',
        'description',
        'priority',
        'content',
        'test_model_id',
    ];

    protected $casts = [
        'test_model_id' => 'integer',
        'priority' => 'integer',
    ];
}
