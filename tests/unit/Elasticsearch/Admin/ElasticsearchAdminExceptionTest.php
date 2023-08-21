<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\ElasticsearchAdminException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package system-settings
 *
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Admin\ElasticsearchAdminException
 */
#[Package('system-settings')]
class ElasticsearchAdminExceptionTest extends TestCase
{
    public function testAdminEsNotEnabled(): void
    {
        $exception = ElasticsearchAdminException::esNotEnabled();

        static::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $exception->getStatusCode());
        static::assertSame('Admin elasticsearch is not enabled', $exception->getMessage());
        static::assertSame(ElasticsearchAdminException::ADMIN_ELASTIC_SEARCH_NOT_ENABLED, $exception->getErrorCode());
    }
}
