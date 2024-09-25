<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\WebConnect\Model\Buffer;

class Order_Model {

    private int $id;
    private int $cart_id;
    private float $price;
    private string $currency;
    /** @var array<Product_Model> */
    private array $products;

    public function __construct(
        int $id,
        int $cart_id,
        float $price,
        string $currency,
        array $products
    ) {
        $this->id       = $id;
        $this->cart_id  = $cart_id;
        $this->price    = $price;
        $this->currency = $currency;
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
            'cartId'   => (string) $this->cart_id,
            'orderId'  => (string) $this->id,
            'currency' => $this->currency,
            'products' => $products,
		];
    }
}
