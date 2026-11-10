<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\NumberRange\Telemetry;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\NumberRange\Telemetry\IncrementStorageMetricsDecorator;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;

/**
 * Proves the one thing the unit test cannot: that the metrics decorator is actually wired around the
 * configured increment storage. The emitted metric and its labels are covered by the unit test.
 *
 * @internal
 */
#[Package('framework')]
class IncrementStorageMetricsDecoratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testActiveIncrementStorageIsDecorated(): void
    {
        $storage = static::getContainer()->get(AbstractIncrementStorage::class);

        static::assertInstanceOf(IncrementStorageMetricsDecorator::class, $storage);
        static::assertNotInstanceOf(IncrementStorageMetricsDecorator::class, $storage->getDecorated());
    }
}
