<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

/**
 * @internal
 */
interface BusinessEventEncoderTestInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getEncodeValues(string $shopwareVersion): array;
}
