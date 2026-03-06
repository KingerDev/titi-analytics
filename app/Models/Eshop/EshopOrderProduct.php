<?php

namespace App\Models\Eshop;

use Illuminate\Database\Eloquent\Model;

class EshopOrderProduct extends Model
{
    protected $table = 'titi_order_product';
    protected $primaryKey = 'order_product_id';
    public $timestamps = false;

    protected $fillable = [];

    public function order()
    {
        return $this->belongsTo(EshopOrder::class, 'order_id', 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(EshopProduct::class, 'product_id', 'product_id');
    }
}
