<?php

namespace Shopware\Core\Framework\Api\HealthCheck\Service;

use Shopware\Core\Framework\Api\HealthCheck\Model\Result;

interface Check
{
    public function run(): Result;

    public function priority(): int;
}
