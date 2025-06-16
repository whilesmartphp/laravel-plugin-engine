<?php

namespace Trakli\PluginEngine\Tests\Stubs\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory;

    protected $guarded = [];
}
