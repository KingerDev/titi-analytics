<?php

namespace App\Models\Eshop;

use Illuminate\Database\Eloquent\Model;

class EshopCustomer extends Model
{
    protected $table = 'titi_customer';
    protected $primaryKey = 'customer_id';
    public $timestamps = false;

    protected $fillable = [];

    public function orders()
    {
        return $this->hasMany(EshopOrder::class, 'customer_id', 'customer_id');
    }

    public function getRegistrationMethodAttribute(): string
    {
        if ($this->google_id) return 'google';
        if ($this->apple_id) return 'apple';
        return 'email';
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }
}
