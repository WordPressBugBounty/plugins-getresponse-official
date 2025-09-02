<?php

declare(strict_types=1);

namespace GR\WordPress\Integrations\WebConnect\Model\Buffer;

class Order_Model {

	private int $id;
	private string $cart_id;
	private float $price;
	private string $currency;
	/** @var array<Product_Model> */
	private array $products;

	public function __construct(
		int $id,
		string $cart_id,
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
		$products = array();

		foreach ( $this->products as $product ) {

			$categories = array();

			foreach ( $product->get_categories() as $category ) {
				$categories[] = $category->to_array();
			}

			$products[] = array(
				'product'    => $product->to_array(),
				'quantity'   => $product->get_quantity(),
				'categories' => $categories,
			);
		}

		return array(
			'price'    => $this->price,
			'cartId'   => (string) $this->cart_id,
			'orderId'  => (string) $this->id,
			'currency' => $this->currency,
			'products' => $products,
		);
	}
}
