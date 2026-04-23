<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Core;

class Gr_Configuration_Validator {

	const VALIDATOR_URL          = 'https://app.getresponse.com/integrations/verify-configuration-signature';
	const SUCCESS_RESPONSE_VALUE = 'success';
	const VERIFICATION_STATUS    = 'verification_status';

	private Gr_Configuration_Http_Client $client;

	public function __construct( Gr_Configuration_Http_Client $client ) {
		$this->client = $client;
	}

	public function validate( array $configuration, array $headers ) {
		$response = $this->client->post( self::VALIDATOR_URL, $configuration, $headers );
		$response = json_decode( $response['body'], true );
		if ( $response[ self::VERIFICATION_STATUS ] !== self::SUCCESS_RESPONSE_VALUE ) {
			throw new Gr_Configuration_Validator_Exception( 'Configuration Verification failed' );
		}
	}
}
