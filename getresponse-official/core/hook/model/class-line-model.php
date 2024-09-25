<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook\Model;

class Line_Model implements Model {

    private int $variant_id;
    private float $price;
    private float $price_tax;
    private int $quantity;
    private string $sku;

    public function __construct(
        int $variant_id,
        float $price,
        float $price_tax,
        int $quantity,
        string $sku
    ) {
        $this->variant_id = $variant_id;
        $this->price      = $price;
        $this->price_tax  = $price_tax;
        $this->quantity   = $quantity;
        $this->sku        = $sku;
    }

    public function to_api_callback(): array {
        return [
            'variant_id' => $this->variant_id,
            'price'      => $this->price,
            'price_tax'  => $this->price_tax,
            'quantity'   => $this->quantity,
            'sku'        => $this->sku,
        ];
    }
}
