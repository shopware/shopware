<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException
 *
 * @internal
 */
#[Package('checkout')]
class DataAbstractionLayerExceptionTest extends TestCase
{
    public function testInvalidCronIntervalFormat(): void
    {
        $e = DataAbstractionLayerException::invalidCronIntervalFormat('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_CRON_INTERVAL_CODE, $e->getErrorCode());
        static::assertSame('Unknown or bad CronInterval format "foo".', $e->getMessage());
    }

    public function testInvalidDateIntervalFormat(): void
    {
        $e = DataAbstractionLayerException::invalidDateIntervalFormat('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_DATE_INTERVAL_CODE, $e->getErrorCode());
        static::assertSame('Unknown or bad DateInterval format "foo".', $e->getMessage());
    }
}
