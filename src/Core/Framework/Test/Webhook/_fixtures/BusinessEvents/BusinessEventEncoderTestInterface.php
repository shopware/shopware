<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

interface BusinessEventEncoderTestInterface
{
    public function getEncodeValues(string $shopwareVersion): array;
}
