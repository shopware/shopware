<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class ThemeAppLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    private EventDispatcherInterface $eventDispatcher;

    private EntityRepository $themeRepository;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $this->themeRepository = $this->getContainer()->get('theme.repository');
    }

    public function testHandleInstall(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/theme');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));

        /** @var ThemeCollection $themes */
        $themes = $this->themeRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(1, $themes);
        static::assertTrue($themes->first()->isActive());
    }

    public function testHandleUpdateIfNotActivated(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/theme', false);
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/fixtures/Apps/theme/manifest.xml');

        $this->eventDispatcher->dispatch(
            new AppUpdatedEvent(
                (new AppEntity())->assign([
                    'active' => false,
                    'name' => 'SwagTheme',
                    'path' => str_replace(
                        $this->getContainer()->getParameter('kernel.project_dir') . '/',
                        '',
                        $manifest->getPath()
                    ),
                ]),
                $manifest,
                Context::createDefaultContext()
            )
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $manifest->getMetadata()->getName()));

        /** @var ThemeCollection $themes */
        $themes = $this->themeRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(0, $themes);
    }

    public function testHandleUninstallIfNotInstalled(): void
    {
        $this->eventDispatcher->dispatch(
            new AppDeactivatedEvent(
                (new AppEntity())->assign([
                    'name' => 'SwagTheme',
                ]),
                Context::createDefaultContext()
            )
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));

        /** @var ThemeCollection $themes */
        $themes = $this->themeRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(0, $themes);
    }

    public function testHandleUninstallDeactivatesTheme(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/theme');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));
        /** @var ThemeCollection $themes */
        $themes = $this->themeRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(1, $themes);
        static::assertTrue($themes->first()->isActive());

        $this->eventDispatcher->dispatch(
            new AppDeactivatedEvent(
                (new AppEntity())->assign([
                    'name' => 'SwagTheme',
                ]),
                Context::createDefaultContext()
            )
        );

        /** @var ThemeCollection $themes */
        $themes = $this->themeRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(1, $themes);
        static::assertFalse($themes->first()->isActive());
    }
}
