<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context;

use Shopware\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;

#[Package('framework')]
interface ContextGatewayInterface
{
    public function process(ContextGatewayPayloadStruct $payload): ContextTokenResponse;
}
