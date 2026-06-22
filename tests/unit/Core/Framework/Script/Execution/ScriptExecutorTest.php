<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Api\ApiHook;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Script\Execution\InterfaceHook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptAppInformation;
use Shopware\Core\Framework\Script\Execution\ScriptEnvironmentFactory;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Script\Execution\ScriptLoader;
use Shopware\Core\Framework\Script\ScriptException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(ScriptExecutor::class)]
class ScriptExecutorTest extends TestCase
{
    public function testThrowsIfHookIsInterfaceHook(): void
    {
        $scriptExecutor = new ScriptExecutor(
            $this->createMock(ScriptLoader::class),
            $this->createMock(ActiveAppsLoader::class),
            $this->createMock(ScriptTraces::class),
            $this->createMock(ContainerInterface::class),
            $this->createMock(ScriptEnvironmentFactory::class),
        );

        try {
            $scriptExecutor->execute($this->createMock(InterfaceHook::class));
        } catch (ScriptException $e) {
            static::assertSame(ScriptException::INTERFACE_HOOK_EXECUTION_NOT_ALLOWED, $e->getErrorCode());
        }
    }

    public function testSkipsScriptWhenAppIsNotActive(): void
    {
        $loader = $this->createMock(ScriptLoader::class);
        $loader
            ->expects($this->once())
            ->method('get')
            ->with('api-service-endpoint')
            ->willReturn([
                new Script(
                    'service-script.twig',
                    '',
                    new \DateTimeImmutable(),
                    new ScriptAppInformation('app-id', 'SwagService', '1.0.0', 'integration-id'),
                ),
            ]);

        $activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $activeAppsLoader
            ->expects($this->once())
            ->method('isActive')
            ->with('SwagService')
            ->willReturn(false);

        $scriptEnvironmentFactory = $this->createMock(ScriptEnvironmentFactory::class);
        $scriptEnvironmentFactory
            ->expects($this->never())
            ->method('initEnv');

        $scriptExecutor = new ScriptExecutor(
            $loader,
            $activeAppsLoader,
            $this->createMock(ScriptTraces::class),
            $this->createMock(ContainerInterface::class),
            $scriptEnvironmentFactory,
        );

        $scriptExecutor->execute(new ApiHook('service-endpoint', [], Context::createDefaultContext()));
    }
}
