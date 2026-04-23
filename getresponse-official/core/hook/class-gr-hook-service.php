<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Core\Hook;

use GetResponse\WordPress\Core\Gr_Configuration;
use GetResponse\WordPress\Core\Hook\Model\Model;

class Gr_Hook_Service {

	private Gr_Hook_Client $client;

	public function __construct( Gr_Hook_Client $client ) {
		$this->client = $client;
	}

	/**
	 * @throws Gr_Hook_Exception
	 */
	public function send_callback(
		Gr_Configuration $configuration,
		Model $model
	): void {
		$this->client->post( $configuration->get_live_sync_url(), $model->to_api_callback() );
	}
}
