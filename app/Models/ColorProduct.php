<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColorProduct extends Model
{
    use HasFactory;
    public function variant()
    {
        return $this->hasMany(VariantColors::class,'color_id');
    }
}
