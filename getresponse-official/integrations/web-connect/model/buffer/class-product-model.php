<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\WebConnect\Model\Buffer;

class Product_Model {

    private int $id;
    private string $name;
    private float $price;
    private string $sku;
    private string $currency;
    private int $quantity;
    /** @var array<Category_Model> */
    private array $categories;

    public function __construct(
        int $id,
        string $name,
        float $price,
        string $sku,
        string $currency,
        int $quantity,
        array $categories
    ) {
        $this->id         = $id;
        $this->name       = $name;
        $this->price      = $price;
        $this->sku        = $sku;
        $this->currency   = $currency;
        $this->quantity   = $quantity;
        $this->categories = $categories;
    }

    public function get_categories(): array {
        return $this->categories;
    }

    public function get_quantity(): int {
        return $this->quantity;
    }

    public function to_array(): array {
        return [
            'id'       => (string) $this->id,
            'name'     => $this->name,
            'price'    => (string) $this->price,
            'sku'      => $this->sku,
            'currency' => $this->currency,
        ];
    }
}
