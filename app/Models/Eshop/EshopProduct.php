<?php

namespace App\Models\Eshop;

use Illuminate\Database\Eloquent\Model;

class EshopProduct extends Model
{
    protected $table = 'titi_product';
    protected $primaryKey = 'product_id';
    public $timestamps = false;

    protected $fillable = [];

    public function description()
    {
        return $this->hasOne(EshopProductDescription::class, 'product_id', 'product_id');
    }

    public function isActive(): bool
    {
        return $this->status == 1
            && $this->titi_eshop == 1
            && $this->mopcena > 0
            && $this->quantity > 1;
    }
}
