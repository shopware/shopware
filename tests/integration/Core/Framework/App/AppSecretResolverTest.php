<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppSecretResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class AppSecretResolverTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private AppSecretResolver $resolver;

    private Connection $connection;

    private AppFixture $appFixture;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        // constructed directly: the service is private and has no consumer yet, so the
        // compiled container removes it
        $this->resolver = new AppSecretResolver($this->connection);

        $appFixture = $this->getContainer()->get(AppFixture::class);
        static::assertInstanceOf(AppFixture::class, $appFixture);
        $this->appFixture = $appFixture;
    }

    public function testResolvesTheCurrentSecret(): void
    {
        $app = $this->appFixture->createAppFromData(['appSecret' => 's3cr3t']);

        static::assertSame('s3cr3t', $this->resolver->resolve($app->getName()));
    }

    public function testResolvesTheRotatedSecretOnTheNextCall(): void
    {
        $app = $this->appFixture->createAppFromData(['appSecret' => 's3cr3t']);
        static::assertSame('s3cr3t', $this->resolver->resolve($app->getName()));

        // a rotation writes a new secret directly; the resolver reads it fresh on the next call
        $this->connection->update('app', ['app_secret' => 'rotated'], ['name' => $app->getName()]);

        static::assertSame('rotated', $this->resolver->resolve($app->getName()));
    }

    public function testReturnsNullWhenTheAppHasNoSecret(): void
    {
        $app = $this->appFixture->createAppFromData();

        static::assertNull($this->resolver->resolve($app->getName()));
    }

    public function testReturnsNullForAnUnknownApp(): void
    {
        static::assertNull($this->resolver->resolve('does-not-exist'));
    }
}
