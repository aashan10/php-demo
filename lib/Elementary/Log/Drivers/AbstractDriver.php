<?php

declare(strict_types=1);

namespace Elementary\Log\Drivers;

use Elementary\Config\ConfigBag;
use Psr\Log\LoggerInterface;

abstract class AbstractDriver implements LoggerInterface
{
    public function __construct(protected ConfigBag $config) {
        $this->initialize();
    }

    abstract protected function initialize(): void;

}
