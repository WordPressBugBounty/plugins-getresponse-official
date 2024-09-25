<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\WebConnect\Model\Buffer;

class Category_Model {

    private int $id;
    private string $name;

    public function __construct( int $id, string $name ) {
        $this->id   = $id;
        $this->name = $name;
    }

    public function to_array(): array {
        return [
            'id'   => (string) $this->id,
            'name' => $this->name,
        ];
    }
}
