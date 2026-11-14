<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AbstractExtensionLifecycle;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AbstractExtensionLifecycle::class)]
class AbstractExtensionLifecycleTest extends TestCase
{
    public function testDecoratorWithoutComposerMethodsPassesComposerRequireToTheDecoratedService(): void
    {
        $decorated = $this->createMock(AbstractExtensionLifecycle::class);
        $decorated->expects($this->once())
            ->method('composerRequire')
            ->with('plugin', 'SwagTest')
            ->willReturn(true);

        $decorator = new ExtensionLifecycleDecoratorWithoutComposerMethods($decorated);

        static::assertTrue($decorator->composerRequire('plugin', 'SwagTest', Context::createDefaultContext()));
    }

    public function testDecoratorWithoutComposerMethodsPassesComposerRemoveToTheDecoratedService(): void
    {
        $decorated = $this->createMock(AbstractExtensionLifecycle::class);
        $decorated->expects($this->once())
            ->method('composerRemove')
            ->with('plugin', 'SwagTest')
            ->willReturn(false);

        $decorator = new ExtensionLifecycleDecoratorWithoutComposerMethods($decorated);

        static::assertFalse($decorator->composerRemove('plugin', 'SwagTest', Context::createDefaultContext()));
    }
}

/**
 * @internal
 */
class ExtensionLifecycleDecoratorWithoutComposerMethods extends AbstractExtensionLifecycle
{
    public function __construct(private readonly AbstractExtensionLifecycle $decorated)
    {
    }

    public function install(string $type, string $technicalName, Context $context): void
    {
        $this->decorated->install($type, $technicalName, $context);
    }

    public function update(string $type, string $technicalName, bool $allowNewPermissions, Context $context): void
    {
        $this->decorated->update($type, $technicalName, $allowNewPermissions, $context);
    }

    public function uninstall(string $type, string $technicalName, bool $keepUserData, Context $context): void
    {
        $this->decorated->uninstall($type, $technicalName, $keepUserData, $context);
    }

    public function activate(string $type, string $technicalName, Context $context): void
    {
        $this->decorated->activate($type, $technicalName, $context);
    }

    public function deactivate(string $type, string $technicalName, Context $context): void
    {
        $this->decorated->deactivate($type, $technicalName, $context);
    }

    public function remove(string $type, string $technicalName, bool $keepUserData, Context $context): void
    {
        $this->decorated->remove($type, $technicalName, $keepUserData, $context);
    }

    protected function getDecorated(): AbstractExtensionLifecycle
    {
        return $this->decorated;
    }
}
