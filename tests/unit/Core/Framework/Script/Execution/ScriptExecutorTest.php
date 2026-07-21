<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
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
#[Package('framework')]
#[CoversClass(ScriptExecutor::class)]
class ScriptExecutorTest extends TestCase
{
    public function testThrowsIfHookIsInterfaceHook(): void
    {
        $scriptExecutor = new ScriptExecutor(
            static::createStub(ScriptLoader::class),
            static::createStub(ScriptTraces::class),
            static::createStub(ContainerInterface::class),
            static::createStub(ScriptEnvironmentFactory::class),
        );

        try {
            $scriptExecutor->execute(static::createStub(InterfaceHook::class));
        } catch (ScriptException $e) {
            static::assertSame(ScriptException::INTERFACE_HOOK_EXECUTION_NOT_ALLOWED, $e->getErrorCode());
        }
    }
}
