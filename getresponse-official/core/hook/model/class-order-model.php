<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook\Model;

use GR\Wordpress\Core\Hook\Gr_Hook_Type;

class Order_Model implements Model {

    private int $id;
    private string $order_number;
    private ?int $cart_id;
    private string $contact_email;
    private User_Model $customer;
    /** @var array<Line_Model> */
    private array $lines;
    private ?string $url;
    private float $total_price;
    private float $total_price_tax;
    private float $shipping_price;
    private string $currency;
    private string $status;
    private ?Address_Model $shipping_address;
    private ?Address_Model $billing_address;
    private string $created_at;
    private ?string $updated_at;

    /**
     * @param array<Line_Model> $lines
     */
    public function __construct(
        int $id,
        string $order_number,
        ?int $cart_id,
        string $contact_email,
        User_Model $customer,
        array $lines,
        ?string $url,
        float $total_price,
        float $total_price_tax,
        float $shipping_price,
        string $currency,
        string $status,
        ?Address_Model $shipping_address,
        ?Address_Model $billing_address,
        string $created_at,
        ?string $updated_at
    ) {
        $this->id               = $id;
        $this->order_number     = $order_number;
        $this->cart_id          = $cart_id;
        $this->contact_email    = $contact_email;
        $this->customer         = $customer;
        $this->lines            = $lines;
        $this->url              = $url;
        $this->total_price      = $total_price;
        $this->total_price_tax  = $total_price_tax;
        $this->shipping_price   = $shipping_price;
        $this->currency         = $currency;
        $this->status           = $status;
        $this->shipping_address = $shipping_address;
        $this->billing_address  = $billing_address;
        $this->created_at       = $created_at;
        $this->updated_at       = $updated_at;
    }

    public function to_api_callback(): array {
        $lines = [];
        foreach ( $this->lines as $line ) {
            $lines[] = $line->to_api_callback();
        }

        return [
            'callback_type'    => Gr_Hook_Type::ORDERS_UPDATE,
            'id'               => $this->id,
            'order_number'     => $this->order_number,
            'cart_id'          => $this->cart_id,
            'contact_email'    => $this->contact_email,
            'customer'         => $this->customer->to_api_callback(),
            'lines'            => $lines,
            'url'              => $this->url,
            'total_price'      => $this->total_price,
            'total_price_tax'  => $this->total_price_tax,
            'shipping_price'   => $this->shipping_price,
            'currency'         => $this->currency,
            'status'           => $this->status,
            'billing_status'   => $this->status,
            'shipping_address' => null !== $this->shipping_address ? $this->shipping_address->to_api_callback() : [],
            'billing_address'  => null !== $this->billing_address ? $this->billing_address->to_api_callback() : [],
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
