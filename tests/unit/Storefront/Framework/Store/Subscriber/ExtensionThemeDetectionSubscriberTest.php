<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Store\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Event\ExtensionLoadedEvent;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Storefront\Framework\Store\Subscriber\ExtensionThemeDetectionSubscriber;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\MockStorefront\MockStorefront;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ExtensionThemeDetectionSubscriber::class)]
class ExtensionThemeDetectionSubscriberTest extends TestCase
{
    #[TestDox('Subscribes to the single extension-loaded event')]
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                ExtensionLoadedEvent::class => 'detectTheme',
            ],
            ExtensionThemeDetectionSubscriber::getSubscribedEvents(),
        );
    }

    #[TestDox('A plugin whose base class implements ThemeInterface is flagged as theme')]
    public function testDetectThemeFlagsThemePlugin(): void
    {
        $subscriber = new ExtensionThemeDetectionSubscriber(static::createStub(EntityRepository::class));

        $plugin = new PluginEntity();
        $plugin->assign(['baseClass' => MockStorefront::class]);

        $extension = new ExtensionStruct();
        $subscriber->detectTheme(new ExtensionLoadedEvent($plugin, $extension, Context::createDefaultContext()));

        static::assertTrue($extension->isTheme());
    }

    #[TestDox('A plugin leaves isTheme false: $_dataName')]
    #[DataProvider('nonThemePluginBaseClassProvider')]
    public function testDetectThemeLeavesNonThemePluginUntouched(string $baseClass): void
    {
        $subscriber = new ExtensionThemeDetectionSubscriber(static::createStub(EntityRepository::class));

        $plugin = new PluginEntity();
        $plugin->assign(['baseClass' => $baseClass]);

        $extension = new ExtensionStruct();
        $subscriber->detectTheme(new ExtensionLoadedEvent($plugin, $extension, Context::createDefaultContext()));

        static::assertFalse($extension->isTheme());
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function nonThemePluginBaseClassProvider(): \Generator
    {
        yield 'non-existent class' => ['NonExistent\\Class\\Name'];
        yield 'existing class that does not implement ThemeInterface' => [\stdClass::class];
    }

    #[TestDox('An app whose name is among the installed themes is flagged as theme')]
    public function testDetectThemeFlagsInstalledThemeApp(): void
    {
        $subscriber = new ExtensionThemeDetectionSubscriber(
            $this->buildThemeRepositoryReturning(['MyTheme', 'OtherTheme'])
        );

        $extension = new ExtensionStruct();
        $subscriber->detectTheme(new ExtensionLoadedEvent($this->buildApp('MyTheme'), $extension, Context::createDefaultContext()));

        static::assertTrue($extension->isTheme());
    }

    #[TestDox('An app leaves isTheme false when its name is not among the installed themes')]
    public function testDetectThemeLeavesNonThemeAppUntouched(): void
    {
        $subscriber = new ExtensionThemeDetectionSubscriber(
            $this->buildThemeRepositoryReturning(['SomeOtherTheme'])
        );

        $extension = new ExtensionStruct();
        $subscriber->detectTheme(new ExtensionLoadedEvent($this->buildApp('NotATheme'), $extension, Context::createDefaultContext()));

        static::assertFalse($extension->isTheme());
    }

    #[TestDox('Installed theme names are cached across calls and re-fetched after reset()')]
    public function testInstalledThemeNameLookupIsCachedAndResettable(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->exactly(2))
            ->method('aggregate')
            ->willReturn(
                $this->buildAggregationResult(['MyTheme'])
            );

        $subscriber = new ExtensionThemeDetectionSubscriber($repository);
        $context = Context::createDefaultContext();

        $first = new ExtensionStruct();
        $second = new ExtensionStruct();
        $subscriber->detectTheme(new ExtensionLoadedEvent($this->buildApp('MyTheme'), $first, $context));
        $subscriber->detectTheme(new ExtensionLoadedEvent($this->buildApp('MyTheme'), $second, $context));

        static::assertTrue($first->isTheme());
        static::assertTrue($second->isTheme());

        $subscriber->reset();

        $third = new ExtensionStruct();
        $subscriber->detectTheme(new ExtensionLoadedEvent($this->buildApp('MyTheme'), $third, $context));

        static::assertTrue($third->isTheme());
    }

    /**
     * @param array<string> $themeNames
     *
     * @return EntityRepository<ThemeCollection>
     */
    private function buildThemeRepositoryReturning(array $themeNames): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('aggregate')->willReturn($this->buildAggregationResult($themeNames));

        return $repository;
    }

    /**
     * @param array<string> $themeNames
     */
    private function buildAggregationResult(array $themeNames): AggregationResultCollection
    {
        $buckets = array_map(
            static fn (string $name): Bucket => new Bucket($name, 1, null),
            array_values($themeNames),
        );

        $collection = new AggregationResultCollection();
        $collection->add(new TermsResult('theme_names', $buckets));

        return $collection;
    }

    private function buildApp(string $name): AppEntity
    {
        $app = new AppEntity();
        $app->assign(['id' => 'app-id-' . $name]);
        $app->setName($name);

        return $app;
    }
}
