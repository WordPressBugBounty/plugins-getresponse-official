<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations;

interface Integration {

    public function init(): void;
}
