<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook;

class Gr_Hook_Type {

    public const CUSTOMERS_CREATE   = 'customers/create';
    public const PRODUCTS_UPDATE    = 'products/update';
    public const SUBSCRIBERS_CREATE = 'subscribers/create';
    public const ORDERS_UPDATE      = 'orders/update';
    public const CHECKOUTS_UPDATE   = 'checkouts/update';

}
