<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Api\AclFacadeHookFactory;
use Shopware\Core\Framework\Script\AppContextCreator;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
#[CoversClass(AclFacadeHookFactory::class)]
class AclFacadeHookFactoryTest extends TestCase
{
    public function testFactory(): void
    {
        $appContextCreator = $this->createMock(AppContextCreator::class);
        $hook = $this->createMock(Hook::class);

        $script = new Script('my-script', '', new \DateTimeImmutable());
        $context = Context::createCLIContext();

        $appContextCreator
            ->expects($this->once())
            ->method('getAppContext')
            ->with($hook, $script)
            ->willReturn($context);

        $factory = new AclFacadeHookFactory($appContextCreator);

        static::assertSame('acl', $factory->getName());

        $factory->factory($hook, $script);
    }
}
