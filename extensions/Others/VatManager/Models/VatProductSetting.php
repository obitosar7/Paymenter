<?php

namespace Paymenter\Extensions\Others\VatManager\Models;

use App\Models\Model;

class VatProductSetting extends Model
{
    protected $table = 'vat_product_settings';

    protected $fillable = ['product_id', 'enabled'];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
