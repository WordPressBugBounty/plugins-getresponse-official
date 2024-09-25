<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook\Model;

class Variant_Model implements Model {

    private int $id;
    private string $name;
    private string $sku;
    private float $price;
    private float $price_tax;
    private ?float $previous_price;
    private ?float $previous_price_tax;
    private int $quantity;
    private string $url;
    private ?int $position;
    private ?int $barcode;
    private string $short_description;
    private string $description;
    /** @var array<Image_Model> */
    private array $images;
    private string $status;
    private ?float $sale_price;
    private ?string $sale_starts_at;
    private ?string $sale_ends_at;

    /**
     * @param array<Image_Model> $images
     */
    public function __construct(
        int $id,
        string $name,
        string $sku,
        float $price,
        float $price_tax,
        ?float $previous_price,
        ?float $previous_price_tax,
        int $quantity,
        string $url,
        ?int $position,
        ?int $barcode,
        string $short_description,
        string $description,
        array $images,
        string $status,
        ?float $sale_price,
        ?string $sale_starts_at,
        ?string $sale_ends_at
    ) {
        $this->id                 = $id;
        $this->name               = $name;
        $this->sku                = $sku;
        $this->price              = $price;
        $this->price_tax          = $price_tax;
        $this->previous_price     = $previous_price;
        $this->previous_price_tax = $previous_price_tax;
        $this->quantity           = $quantity;
        $this->url                = $url;
        $this->position           = $position;
        $this->barcode            = $barcode;
        $this->short_description  = $short_description;
        $this->description        = $description;
        $this->images             = $images;
        $this->status             = $status;
        $this->sale_price         = $sale_price;
        $this->sale_starts_at     = $sale_starts_at;
        $this->sale_ends_at       = $sale_ends_at;
    }

    public function to_api_callback(): array {
        $images = [];
        foreach ( $this->images as $image ) {
            $images[] = $image->to_api_callback();
        }

		return [
			'id'                 => $this->id,
			'name'               => $this->name,
			'sku'                => $this->sku,
			'price'              => $this->price,
			'price_tax'          => $this->price_tax,
			'previous_price'     => $this->previous_price,
			'previous_price_tax' => $this->previous_price_tax,
			'quantity'           => $this->quantity,
			'url'                => $this->url,
			'position'           => $this->position,
			'barcode'            => $this->barcode,
			'short_description'  => $this->short_description,
			'description'        => $this->description,
			'images'             => $images,
            'status'             => $this->status,
            'sale_price'         => $this->sale_price,
            'sale_starts_at'     => $this->sale_starts_at,
            'sale_ends_at'       => $this->sale_ends_at,
		];
    }
}
