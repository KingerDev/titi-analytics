<?php

namespace App\Models\Eshop;

use Illuminate\Database\Eloquent\Model;

class EshopProductDescription extends Model
{
    protected $table = 'titi_product_description';
    protected $primaryKey = 'product_id';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [];
}
