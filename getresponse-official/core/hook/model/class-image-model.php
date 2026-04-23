<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Core\Hook\Model;

class Image_Model implements Model {

	private string $src;
	private int $position;

	public function __construct( string $src, int $position ) {
		$this->src      = $src;
		$this->position = $position;
	}

	public function to_api_callback(): array {
		return array(
			'src'      => $this->src,
			'position' => $this->position,
		);
	}
}
