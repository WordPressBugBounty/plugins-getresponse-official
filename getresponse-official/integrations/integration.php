<?php

declare(strict_types=1);

namespace GR\WordPress\Integrations;

interface Integration {

	public function init(): void;
}
