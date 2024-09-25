<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook\Model;

class Address_Model implements Model {

    private string $name;
    private string $country_code;
    private string $first_name;
    private string $last_name;
    private string $address1;
    private ?string $address2;
    private string $city;
    private string $zip;
    private ?string $province;
    private ?string $province_code;
    private ?string $phone;
    private ?string $company;

    public function __construct(
        string $country_code,
        string $first_name,
        string $last_name,
        string $address1,
        ?string $address2,
        string $city,
        string $zip,
        ?string $province,
        ?string $province_code,
        ?string $phone,
        ?string $company
    ) {
        $this->name          = sprintf( '%s %s', $first_name, $last_name );
        $this->country_code  = $country_code;
        $this->first_name    = $first_name;
        $this->last_name     = $last_name;
        $this->address1      = $address1;
        $this->address2      = $address2;
        $this->city          = $city;
        $this->zip           = $zip;
        $this->province      = $province;
        $this->province_code = $province_code;
        $this->phone         = $phone;
        $this->company       = $company;
    }

    public static function fromRawData( array $raw_data ): self {
        return new self(
            $raw_data['country'] ?? '',
            $raw_data['first_name'] ?? '',
            $raw_data['last_name'] ?? '',
            $raw_data['address_1'] ?? '',
            $raw_data['address_2'],
            $raw_data['city'] ?? '',
            $raw_data['postcode'] ?? '',
            $raw_data['state'],
            null,
            $raw_data['phone'],
            $raw_data['company']
        );
    }

    public function to_api_callback(): array {
        return [
            'name'          => $this->name,
            'country_code'  => $this->country_code,
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'address1'      => $this->address1,
            'address2'      => $this->address2,
            'city'          => $this->city,
            'zip'           => $this->zip,
            'province'      => $this->province,
            'province_code' => $this->province_code,
            'phone'         => $this->phone,
            'company'       => $this->company,
        ];
    }
}
