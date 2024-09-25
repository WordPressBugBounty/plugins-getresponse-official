<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook\Model;

use GR\Wordpress\Core\Hook\Gr_Hook_Type;

class Contact_Model implements Model {

    private string $email;
    private bool $gr_marketing_consent;
    private ?string $name;
    private ?string $first_name;
    private ?string $last_name;
    private array $custom_fields;
    private array $tags;

    public function __construct(
        string $email,
        bool $gr_marketing_consent,
        ?string $name,
        ?string $first_name,
        ?string $last_name,
        array $custom_fields = [],
        array $tags = []
    ) {
        $this->email                = $email;
        $this->gr_marketing_consent = $gr_marketing_consent;
        $this->name                 = $name;
        $this->first_name           = $first_name;
        $this->last_name            = $last_name;
        $this->custom_fields        = $custom_fields;
        $this->tags                 = $tags;
    }

    public function to_api_callback(): array {

        $first_name = $this->first_name;
        $last_name  = $this->last_name;

        if ( null !== $this->name && null === $this->first_name && null === $this->last_name ) {
            [$first_name, $last_name] = explode( ' ', $this->name );
        }

        return array_merge(
            [
                'callback_type'     => Gr_Hook_Type::SUBSCRIBERS_CREATE,
                'email'             => $this->email,
                'accepts_marketing' => $this->gr_marketing_consent,
                'custom_fields'     => $this->custom_fields,
                'tags'              => $this->tags,
            ],
            array_filter(
                [
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                ]
            )
        );
    }
}
