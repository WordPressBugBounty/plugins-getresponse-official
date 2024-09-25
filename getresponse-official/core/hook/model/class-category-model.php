<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook\Model;

class Category_Model implements Model {

    private int $id;
    private int $parent_id;
    private string $name;
    private bool $is_default;
    private ?string $url;

    public function __construct(
        int $id,
        int $parent_id,
        string $name,
        bool $is_default = false,
        string $url = null
    ) {
        $this->id         = $id;
        $this->parent_id  = $parent_id;
        $this->name       = $name;
        $this->is_default = $is_default;
        $this->url        = $url;
    }

    public function to_api_callback(): array {
        return [
            'id'         => $this->id,
            'parent_id'  => $this->parent_id,
            'name'       => $this->name,
            'is_default' => $this->is_default,
            'url'        => $this->url,
        ];
    }
}
