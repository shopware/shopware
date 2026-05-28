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
use Shopware\Core\Framework\Store\Event\AppExtensionLoadedEvent;
use Shopware\Core\Framework\Store\Event\PluginExtensionLoadedEvent;
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
    #[TestDox('Subscribes to both plugin- and app-loaded events')]
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                PluginExtensionLoadedEvent::class => 'detectPluginTheme',
                AppExtensionLoadedEvent::class => 'detectAppTheme',
            ],
            ExtensionThemeDetectionSubscriber::getSubscribedEvents(),
        );
    }

    #[TestDox('Plugin theme detection marks event when base class implements ThemeInterface')]
    public function testDetectPluginThemeFlipsIsThemeForThemePlugin(): void
    {
        $subscriber = new ExtensionThemeDetectionSubscriber(static::createStub(EntityRepository::class));

        $plugin = new PluginEntity();
        $plugin->assign(['baseClass' => MockStorefront::class]);

        $event = new PluginExtensionLoadedEvent($plugin, Context::createDefaultContext());
        $subscriber->detectPluginTheme($event);

        static::assertTrue($event->isTheme);
    }

    #[TestDox('Plugin theme detection leaves isTheme false: $_dataName')]
    #[DataProvider('nonThemePluginBaseClassProvider')]
    public function testDetectPluginThemeLeavesNonThemeUntouched(string $baseClass): void
    {
        $subscriber = new ExtensionThemeDetectionSubscriber(static::createStub(EntityRepository::class));

        $plugin = new PluginEntity();
        $plugin->assign(['baseClass' => $baseClass]);

        $event = new PluginExtensionLoadedEvent($plugin, Context::createDefaultContext());
        $subscriber->detectPluginTheme($event);

        static::assertFalse($event->isTheme);
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function nonThemePluginBaseClassProvider(): \Generator
    {
        yield 'non-existent class' => ['NonExistent\\Class\\Name'];
        yield 'existing class that does not implement ThemeInterface' => [\stdClass::class];
    }

    #[TestDox('App theme detection marks event when app name is among installed themes')]
    public function testDetectAppThemeFlipsIsThemeForInstalledTheme(): void
    {
        $subscriber = new ExtensionThemeDetectionSubscriber(
            $this->buildThemeRepositoryReturning(['MyTheme', 'OtherTheme'])
        );

        $event = new AppExtensionLoadedEvent($this->buildApp('MyTheme'), Context::createDefaultContext());
        $subscriber->detectAppTheme($event);

        static::assertTrue($event->isTheme);
    }

    #[TestDox('App theme detection leaves isTheme false when app name is not among installed themes')]
    public function testDetectAppThemeLeavesNonThemeUntouched(): void
    {
        $subscriber = new ExtensionThemeDetectionSubscriber(
            $this->buildThemeRepositoryReturning(['SomeOtherTheme'])
        );

        $event = new AppExtensionLoadedEvent($this->buildApp('NotATheme'), Context::createDefaultContext());
        $subscriber->detectAppTheme($event);

        static::assertFalse($event->isTheme);
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

        $first = new AppExtensionLoadedEvent($this->buildApp('MyTheme'), $context);
        $second = new AppExtensionLoadedEvent($this->buildApp('MyTheme'), $context);
        $subscriber->detectAppTheme($first);
        $subscriber->detectAppTheme($second);

        static::assertTrue($first->isTheme);
        static::assertTrue($second->isTheme);

        $subscriber->reset();

        $third = new AppExtensionLoadedEvent($this->buildApp('MyTheme'), $context);
        $subscriber->detectAppTheme($third);

        static::assertTrue($third->isTheme);
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
