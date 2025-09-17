<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

class ProfileModel extends Model
{
    protected $fillable = [
        'bio',
        'website',
        'age',
    ];

    protected $casts = [
        'age' => 'integer',
    ];
}
