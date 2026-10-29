<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\AppContextCreator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptAppInformation;
use Shopware\Core\Framework\Test\Script\Execution\TestHook;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AppContextCreator::class)]
class AppContextCreatorTest extends TestCase
{
    public function testAppContextIsHookContextIfScriptHasNoAppInformation(): void
    {
        $hook = new TestHook('test-hook', Context::createDefaultContext());

        $appContextCreator = new AppContextCreator(static::createStub(Connection::class));

        $appContext = $appContextCreator->getAppContext(
            $hook,
            new DummyScript('dummy-script', null),
        );

        static::assertSame($hook->getContext(), $appContext);
    }

    public function testBuildsDistinctSourcesPerAppContextButCachesPrivileges(): void
    {
        $connection = $this->createMock(Connection::class);
        // Privileges are fetched once and cached; the mutable source must not be shared.
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('["product:read"]');

        $appContextCreator = new AppContextCreator($connection);
        $hook = new TestHook('test-hook', Context::createDefaultContext());
        $script = new DummyScript('dummy-script', Uuid::randomHex());

        $appContext = $appContextCreator->getAppContext($hook, $script);
        $secondAppContext = $appContextCreator->getAppContext($hook, $script);

        static::assertNotSame($appContext->getSource(), $secondAppContext->getSource());
    }

    public function testContextFactoryBuildsAFreshSourceOnEveryCall(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('["product:read"]');

        $appContextCreator = new AppContextCreator($connection);
        $hook = new TestHook('test-hook', Context::createDefaultContext());
        $script = new DummyScript('dummy-script', Uuid::randomHex());

        $factory = $appContextCreator->createAppContextFactory($hook, $script);

        $first = $factory->create();
        $second = $factory->create();

        static::assertNotSame($first, $second);
        static::assertNotSame($first->getSource(), $second->getSource());

        $source = $first->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);
        $source->setIsAdmin(true);

        $secondSource = $second->getSource();
        static::assertInstanceOf(AdminApiSource::class, $secondSource);
        // A change to one source must not reach a context built afterwards.
        static::assertFalse($secondSource->isAdmin());
    }

    public function testContextSourceHasPermissionsIfAppHasPrivileges(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(json_encode([
                'customer:read',
                'order:read',
            ]));

        $appContextCreator = new AppContextCreator($connection);

        $appContext = $appContextCreator->getAppContext(
            new TestHook('test-hook', Context::createDefaultContext()),
            new DummyScript('dummy-script', Uuid::randomHex()),
        );

        $source = $appContext->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);

        static::assertFalse($source->isAdmin());
        static::assertFalse($source->isAllowed('system_config:read'));

        static::assertTrue($source->isAllowed('customer:read'));
        static::assertTrue($source->isAllowed('order:read'));
    }
}

/**
 * @internal
 */
#[Package('framework')]
class DummyScript extends Script
{
    public function __construct(
        string $name,
        ?string $appId,
    ) {
        $app = $appId ? new ScriptAppInformation($appId, '', '', '') : null;

        parent::__construct($name, 'foo', new \DateTimeImmutable(), $app);
    }
}
