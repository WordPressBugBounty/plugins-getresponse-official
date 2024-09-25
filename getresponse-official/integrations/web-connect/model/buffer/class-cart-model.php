<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\WebConnect\Model\Buffer;

class Cart_Model {

    private int $id;
    private float $price;
    private string $currency;
    private string $url;
    /** @var array<Product_Model> */
    private array $products;

    public function __construct(
        int $id,
        float $price,
        string $currency,
        string $url,
        array $products
    ) {
        $this->id       = $id;
        $this->price    = $price;
        $this->currency = $currency;
        $this->url      = $url;
        $this->products = $products;
    }

    public function to_session(): string {
        $data = wp_json_encode( $this->to_array() );

        return is_string( $data ) ? $data : '';
    }

    public function to_array(): array {
        $products = [];

        foreach ( $this->products as $product ) {

            $categories = [];

            foreach ( $product->get_categories() as $category ) {
                $categories[] = $category->to_array();
            }

            $products[] = [
                'product'    => $product->to_array(),
                'quantity'   => $product->get_quantity(),
                'categories' => $categories,
            ];
        }

        return [
            'price'    => $this->price,
            'cartId'   => (string) $this->id,
            'currency' => $this->currency,
            'cartUrl'  => $this->url,
            'products' => $products,
		];
    }
}
