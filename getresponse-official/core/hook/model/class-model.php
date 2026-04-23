<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Core\Hook\Model;

interface Model {

	public function to_api_callback(): array;
}
