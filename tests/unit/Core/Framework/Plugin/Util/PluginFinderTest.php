<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Util;

use Composer\IO\NullIO;
use Composer\Package\CompletePackage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Composer\PackageProvider;
use Shopware\Core\Framework\Plugin\Exception\ExceptionCollection;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginFinder::class)]
class PluginFinderTest extends TestCase
{
    private string $pluginDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginDir = sys_get_temp_dir() . '/' . uniqid('plugin-finder-', true);
        (new Filesystem())->mkdir($this->pluginDir . '/LabelTest');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->pluginDir);
        parent::tearDown();
    }

    public function testFailsOnMissingRootComposerFile(): void
    {
        $errors = new ExceptionCollection();
        (new PluginFinder(new PackageProvider()))->findPlugins(
            __DIR__,
            __DIR__ . '/../../../../../..',
            $errors,
            new NullIO()
        );

        static::assertInstanceOf(PluginComposerJsonInvalidException::class, $errors->first());
    }

    public function testLocalLoadsTheComposerJsonContents(): void
    {
        $plugins = (new PluginFinder(new PackageProvider()))->findPlugins(
            __DIR__ . '/_fixture/LocallyInstalledPlugins',
            __DIR__ . '/_fixture/ComposerProject',
            new ExceptionCollection(),
            new NullIO()
        );
        static::assertCount(2, $plugins);
        static::assertSame($plugins['Swag\Test']->getBaseClass(), 'Swag\Test');
    }

    /*
     * Referring to __DIR__ . '/_fixture/', you can see that we have the same plugin installed locally (residing inside
     * the directory for locally installed plugins) and via composer (residing in the vendor directory and being
     * registered in the installed.json). The Pluginfinder should always consider plugin definitions found via composer
     * over those found in the local directory.
     */
    public function testConsidersComposerInstalledPluginsOverLocalInstalledPlugins(): void
    {
        $plugins = (new PluginFinder(new PackageProvider()))->findPlugins(
            __DIR__ . '/_fixture/LocallyInstalledPlugins',
            __DIR__ . '/_fixture/ComposerProject',
            new ExceptionCollection(),
            new NullIO()
        );

        static::assertInstanceOf(PluginFromFileSystemStruct::class, $plugins['Swag\Test']);
        static::assertTrue($plugins['Swag\Test']->getManagedByComposer());
        // path is still local if it exists
        static::assertSame(__DIR__ . '/_fixture/LocallyInstalledPlugins/SwagTest', $plugins['Swag\Test']->getPath());
        // version info is still from local, as that might be more up to date
        static::assertSame('v1.0.2', $plugins['Swag\Test']->getComposerPackage()->getPrettyVersion());
    }

    public function testComposerPackageFromPluginIsUsedIfNoLocalInstalledVersionExists(): void
    {
        $plugins = (new PluginFinder(new PackageProvider()))->findPlugins(
            __DIR__ . '/_fixture/LocallyInstalledPlugins',
            __DIR__ . '/_fixture/ComposerProject',
            new ExceptionCollection(),
            new NullIO()
        );

        static::assertInstanceOf(PluginFromFileSystemStruct::class, $plugins['Swag\Test2']);
        static::assertTrue($plugins['Swag\Test2']->getManagedByComposer());
        // path is still local if it exists
        static::assertSame(__DIR__ . '/_fixture/ComposerProject/vendor/swag/test2', $plugins['Swag\Test2']->getPath());
        // version info is still from local, as that might be more up to date
        static::assertSame('v2.0.1', $plugins['Swag\Test2']->getComposerPackage()->getPrettyVersion());
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[TestDox('discovers a composer plugin declaring a class and a label')]
    #[DataProvider('acceptedExtrasProvider')]
    public function testLocalPluginWithUsableExtraIsFound(array $extra): void
    {
        static::assertArrayHasKey('Swag\LabelTest', $this->findLocalPlugins($extra));
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[TestDox('drops a composer plugin lacking a usable class or label')]
    #[DataProvider('rejectedExtrasProvider')]
    public function testLocalPluginWithUnusableExtraIsRejected(array $extra): void
    {
        static::assertArrayNotHasKey('Swag\LabelTest', $this->findLocalPlugins($extra));
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[TestDox('records an error for a composer plugin lacking a usable class or label')]
    #[DataProvider('rejectedExtrasProvider')]
    public function testLocalPluginWithUnusableExtraReportsAnError(array $extra): void
    {
        $errors = new ExceptionCollection();
        $this->findLocalPlugins($extra, $errors);

        static::assertCount(1, $errors);
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[TestDox('records no error for a composer plugin declaring a class and a label')]
    #[DataProvider('acceptedExtrasProvider')]
    public function testLocalPluginWithUsableExtraReportsNoError(array $extra): void
    {
        $errors = new ExceptionCollection();
        $this->findLocalPlugins($extra, $errors);

        static::assertCount(0, $errors);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function acceptedExtrasProvider(): iterable
    {
        $class = 'Swag\LabelTest';

        yield 'locale map' => [['shopware-plugin-class' => $class, 'label' => ['en-GB' => 'Label']]];
        yield 'locale map with empty text' => [['shopware-plugin-class' => $class, 'label' => ['en-GB' => '']]];
        yield 'label non-empty string' => [['shopware-plugin-class' => $class, 'label' => 'Label']];
        yield 'label zero string' => [['shopware-plugin-class' => $class, 'label' => '0']];
        yield 'label int' => [['shopware-plugin-class' => $class, 'label' => 1]];
        yield 'label int zero' => [['shopware-plugin-class' => $class, 'label' => 0]];
        yield 'label float' => [['shopware-plugin-class' => $class, 'label' => 1.5]];
        yield 'label float zero' => [['shopware-plugin-class' => $class, 'label' => 0.0]];
        yield 'label true' => [['shopware-plugin-class' => $class, 'label' => true]];
        yield 'label false' => [['shopware-plugin-class' => $class, 'label' => false]];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function rejectedExtrasProvider(): iterable
    {
        $class = 'Swag\LabelTest';

        yield 'class missing' => [['label' => ['en-GB' => 'Label']]];
        yield 'class null' => [['shopware-plugin-class' => null, 'label' => ['en-GB' => 'Label']]];
        yield 'class empty string' => [['shopware-plugin-class' => '', 'label' => ['en-GB' => 'Label']]];
        yield 'label missing' => [['shopware-plugin-class' => $class]];
        yield 'label null' => [['shopware-plugin-class' => $class, 'label' => null]];
        yield 'label empty string' => [['shopware-plugin-class' => $class, 'label' => '']];
        yield 'label empty array' => [['shopware-plugin-class' => $class, 'label' => []]];
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, PluginFromFileSystemStruct>
     */
    private function findLocalPlugins(array $extra, ?ExceptionCollection $errors = null): array
    {
        $package = new CompletePackage('swag/label-test', '1.0.0.0', '1.0.0');
        $package->setType(PluginFinder::COMPOSER_TYPE);
        $package->setExtra($extra);

        $provider = static::createStub(PackageProvider::class);
        $provider->method('getPluginComposerPackage')->willReturn($package);

        $plugins = (new PluginFinder($provider))->findPlugins(
            $this->pluginDir,
            __DIR__ . '/_fixture/ComposerProject',
            $errors ?? new ExceptionCollection(),
            new NullIO()
        );

        /** @var array<string, PluginFromFileSystemStruct> $plugins */
        return $plugins;
    }
}
