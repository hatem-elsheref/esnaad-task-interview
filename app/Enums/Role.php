<?php

namespace App\Enums;

enum Role: string
{
    case Administrator = 'administrator';
    case Merchant = 'merchant';
    case Customer = 'customer';
}
