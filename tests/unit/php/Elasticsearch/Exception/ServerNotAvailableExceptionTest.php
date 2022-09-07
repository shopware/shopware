<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Exception\ServerNotAvailableException;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Exception\ServerNotAvailableException
 */
class ServerNotAvailableExceptionTest extends TestCase
{
    public function testException(): void
    {
        $exception = new ServerNotAvailableException();
        static::assertStringContainsString('Elasticsearch server is not available', $exception->getMessage());
        static::assertSame('ELASTICSEARCH_SERVER_NOT_AVAILABLE', $exception->getErrorCode());
    }
}
