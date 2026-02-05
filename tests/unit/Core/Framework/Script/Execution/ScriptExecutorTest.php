<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Script\Execution\InterfaceHook;
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
}
