<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class KernelTest extends TestCase
{
    use KernelTestBehaviour;

    public function testPostCannotOverrideItsMethodToGet(): void
    {
        $request = Request::create('/api/_info/version', Request::METHOD_POST);
        $request->headers->set('X-HTTP-Method-Override', Request::METHOD_GET);

        $response = static::getKernel()->handle($request);

        static::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        static::assertSame(Request::METHOD_POST, $request->getMethod());
    }

    public function testUTCIsAlwaysSetToDatabase(): void
    {
        $c = static::getContainer()->get(Connection::class);

        static::assertSame($c->fetchOne('SELECT @@session.time_zone'), '+00:00');
    }
}
