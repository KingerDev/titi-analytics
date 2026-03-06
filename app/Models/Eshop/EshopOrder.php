<?php

namespace App\Models\Eshop;

use Illuminate\Database\Eloquent\Model;

class EshopOrder extends Model
{
    protected $table = 'titi_order';
    protected $primaryKey = 'order_id';
    public $timestamps = false;

    protected $fillable = [];

    public function customer()
    {
        return $this->belongsTo(EshopCustomer::class, 'customer_id', 'customer_id');
    }

    public function products()
    {
        return $this->hasMany(EshopOrderProduct::class, 'order_id', 'order_id');
    }
}
