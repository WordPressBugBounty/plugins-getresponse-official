<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Integrations\WebConnect\Model\Buffer;

class Cart_Model {

	private string $id;
	private float $price;
	private string $currency;
	private string $url;
	/** @var array<Product_Model> */
	private array $products;

	public function __construct(
		string $id,
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
			'cartId'   => $this->id,
			'currency' => $this->currency,
			'cartUrl'  => $this->url,
			'products' => $products,
		);
	}
}
