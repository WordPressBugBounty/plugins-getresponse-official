<?php

declare(strict_types=1);

namespace GR\WordPress\Core\Hook\Model;

use GR\WordPress\Core\Hook\Gr_Hook_Type;

class Cart_Model implements Model {

	private string $id;
	private User_Model $customer;
	/** @var array<Line_Model> */
	private array $lines;
	private float $total_price;
	private float $total_tax_price;
	private string $currency;
	private string $url;
	private ?string $visitor_uuid;
	private ?string $created_at;
	private ?string $updated_at;

	/**
	 * @param array<Line_Model> $lines
	 */
	public function __construct(
		string $id,
		User_Model $customer,
		array $lines,
		float $total_price,
		float $total_tax_price,
		string $currency,
		string $url,
		?string $visitor_uuid = null,
		?string $created_at = null,
		?string $updated_at = null
	) {
		$this->id              = $id;
		$this->customer        = $customer;
		$this->lines           = $lines;
		$this->total_price     = $total_price;
		$this->total_tax_price = $total_tax_price;
		$this->currency        = $currency;
		$this->url             = $url;
		$this->visitor_uuid    = $visitor_uuid;
		$this->created_at      = $created_at;
		$this->updated_at      = $updated_at;
	}

	public function to_api_callback(): array {
		$lines = array();
		foreach ( $this->lines as $line ) {
			$lines[] = $line->to_api_callback();
		}

		return array(
			'callback_type'   => Gr_Hook_Type::CHECKOUTS_UPDATE,
			'id'              => $this->id,
			'contact_email'   => $this->customer->getEmail(),
			'customer'        => $this->customer->to_api_callback(),
			'lines'           => $lines,
			'total_price'     => $this->total_price,
			'total_price_tax' => $this->total_tax_price,
			'currency'        => $this->currency,
			'url'             => $this->url,
			'visitor_uuid'    => $this->visitor_uuid,
			'created_at'      => $this->created_at,
			'updated_at'      => $this->updated_at,
		);
	}


	public function is_valuable(): bool {
		return ( ! empty( $this->id ) && ! empty( $this->customer->getEmail() ) ) || ! empty( $this->visitor_uuid );
	}
}
