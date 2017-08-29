<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Shopware\Framework\Api2\SqlGateway;

interface SqlGatewayAware
{
    public function setSqlGateway(SqlGateway $sqlGateway): void;
}