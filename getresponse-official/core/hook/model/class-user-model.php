<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook\Model;

use GR\Wordpress\Core\Hook\Gr_Hook_Type;

class User_Model implements Model {


    private int $id;
    private string $email;
    private bool $gr_marketing_consent;
    private ?string $first_name;
    private ?string $last_name;
    private ?Address_Model $address;
    private array $custom_fields;

    public function __construct(
        int $id,
        string $email,
        bool $gr_marketing_consent,
        ?string $first_name = null,
        ?string $last_name = null,
        ?Address_Model $address = null,
        array $custom_fields = []
    ) {
        $this->id                   = $id;
        $this->email                = $email;
        $this->gr_marketing_consent = $gr_marketing_consent;
        $this->first_name           = $first_name;
        $this->last_name            = $last_name;
        $this->address              = $address;
        $this->custom_fields        = $custom_fields;
    }

    public function getEmail(): string {
        return $this->email;
    }

    public function to_api_callback(): array {
        return array_merge(
            [
                'callback_type'     => Gr_Hook_Type::CUSTOMERS_CREATE,
                'id'                => $this->id,
                'email'             => $this->email,
                'accepts_marketing' => $this->gr_marketing_consent,
                'opt_in_status'     => true,
                'address'           => null !== $this->address ? $this->address->to_api_callback() : [],
                'tags'              => [],
                'custom_fields'     => $this->custom_fields,
            ],
            array_filter(
                [
                    'first_name' => $this->first_name,
                    'last_name'  => $this->last_name,
                ]
            )
        );
    }
}
