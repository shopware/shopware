<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Storefront\Framework\SystemCheck\SaleChannelsReadinessCheck;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;

/**
 * @internal
 */
#[CoversClass(SaleChannelsReadinessCheck::class)]
class SaleChannelsReadinessCheckTest extends TestCase
{
    private SaleChannelsReadinessCheck $salesChannelReadinessCheck;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelReadinessCheck = new SaleChannelsReadinessCheck(
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelDomainUtil::class)
        );
    }

    public function testOnlyAllowedToRunInReadinessContexts(): void
    {
        foreach (SystemCheckExecutionContext::cases() as $context) {
            if (\in_array($context, SystemCheckExecutionContext::readiness(), true)) {
                continue;
            }

            static::assertFalse($this->salesChannelReadinessCheck->allowedToRunIn($context));
        }

        foreach (SystemCheckExecutionContext::readiness() as $context) {
            static::assertTrue($this->salesChannelReadinessCheck->allowedToRunIn($context));
        }
    }
}
