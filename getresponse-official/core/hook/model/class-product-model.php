<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook\Model;

use GR\Wordpress\Core\Hook\Gr_Hook_Type;

class Product_Model implements Model {

    private int $id;
    private string $name;
    private string $type;
    private string $url;
    private string $vendor;
    /** @var array<Category_Model> */
    private array $categories;
    /** @var array<Variant_Model> */
    private array $variants;
    private string $created_at;
    private ?string $updated_at;
    private string $status;

    /**
     * @param array<Category_Model> $categories
     * @param array<Variant_Model> $variants
     */
    public function __construct(
        int $id,
        string $name,
        string $type,
        string $url,
        string $vendor,
        array $categories,
        array $variants,
        string $created_at,
        ?string $updated_at,
        string $status
    ) {
        $this->id         = $id;
        $this->name       = $name;
        $this->type       = $type;
        $this->url        = $url;
        $this->vendor     = $vendor;
        $this->categories = $categories;
        $this->variants   = $variants;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
        $this->status     = $status;
    }

    public function to_api_callback(): array {
        $categories = [];
        foreach ( $this->categories as $category ) {
            $categories[] = $category->to_api_callback();
        }

        $variants = [];
        foreach ( $this->variants as $variant ) {
            $variants[] = $variant->to_api_callback();
        }

        return [
            'callback_type' => Gr_Hook_Type::PRODUCTS_UPDATE,
            'id'            => $this->id,
            'name'          => $this->name,
            'type'          => $this->type,
            'url'           => $this->url,
            'vendor'        => $this->vendor,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'categories'    => $categories,
            'variants'      => $variants,
            'status'        => $this->status,
        ];
    }
}
