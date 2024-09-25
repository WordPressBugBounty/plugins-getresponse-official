<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook\Model;

use GR\Wordpress\Core\Hook\Gr_Hook_Type;

class Cart_Model implements Model {

    private int $id;
    private User_Model $customer;
    /** @var array<Line_Model> */
    private array $lines;
    private float $total_price;
    private float $total_tax_price;
    private string $currency;
    private string $url;
    private ?string $created_at;
    private ?string $updated_at;

    /**
     * @param array<Line_Model> $lines
     */
    public function __construct(
        int $id,
        User_Model $customer,
        array $lines,
        float $total_price,
        float $total_tax_price,
        string $currency,
        string $url,
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
        $this->created_at      = $created_at;
        $this->updated_at      = $updated_at;
    }

    public function to_api_callback(): array {
        $lines = [];
        foreach ( $this->lines as $line ) {
            $lines[] = $line->to_api_callback();
        }

        return [
            'callback_type'   => Gr_Hook_Type::CHECKOUTS_UPDATE,
            'id'              => $this->id,
            'contact_email'   => $this->customer->getEmail(),
            'customer'        => $this->customer->to_api_callback(),
            'lines'           => $lines,
            'total_price'     => $this->total_price,
            'total_price_tax' => $this->total_tax_price,
            'currency'        => $this->currency,
            'url'             => $this->url,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }


    public function is_valuable(): bool {
        return ! empty( $this->id ) && ! empty( $this->customer->getEmail() );
    }
}
