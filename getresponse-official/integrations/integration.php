<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Integrations;

interface Integration {

	public function init(): void;
}
