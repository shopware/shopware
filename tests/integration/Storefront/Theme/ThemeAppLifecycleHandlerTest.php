<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Theme\ThemeCollection;

/**
 * @internal
 */
class ThemeAppLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AppLifecycle $appLifecycle;

    private AppManager $appManager;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    /**
     * @var EntityRepository<ThemeCollection>
     */
    private EntityRepository $themeRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');
        $this->themeRepository = static::getContainer()->get('theme.repository');
        $this->appLifecycle = static::getContainer()->get(AppLifecycle::class);
        $this->appManager = static::getContainer()->get(AppManager::class);
        $this->context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
    }

    #[DataProvider('keepUserDataProvider')]
    public function testThemeRemovedOnUninstall(bool $keepUserData): void
    {
        $app = $this->installThemeApp();

        static::assertCount(1, $this->findThemes($app->getName()));

        $this->appLifecycle->uninstall(
            $app->getName(),
            ['id' => $app->getId(), 'roleId' => $app->getAclRoleId()],
            $this->context,
            $keepUserData
        );

        static::assertCount($keepUserData ? 1 : 0, $this->findThemes($app->getName()));
        static::assertCount(0, $this->appRepository->searchIds(new Criteria(), $this->context)->getIds());
    }

    public function testLocalDeleteRemovesAppWithoutNotifyingAppServerAndLeavesThemeRecord(): void
    {
        $app = $this->installThemeApp();

        static::assertCount(1, $this->findThemes($app->getName()));

        // local-only delete (e.g. the uninstall-apps shop-id strategy): the app server is not notified
        $this->appManager->delete($app, $this->context);

        // the app is gone, but the theme record is intentionally left in place (matches the
        // behaviour for copied shops); only an uninstall removes the theme record
        static::assertCount(0, $this->appRepository->searchIds(new Criteria(), $this->context)->getIds());
        static::assertCount(1, $this->findThemes($app->getName()));
    }

    /**
     * @return array<string, array<int, bool>>
     */
    public static function keepUserDataProvider(): array
    {
        return [
            'keep user data' => [true],
            'remove user data' => [false],
        ];
    }

    private function installThemeApp(): AppEntity
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/fixtures/Apps/theme/manifest.xml');
        $this->appLifecycle->install($manifest, new AppInstallParameters(), $this->context);

        $app = $this->appRepository->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertNotNull($app);

        return $app;
    }

    /**
     * @return array<mixed>
     */
    private function findThemes(string $technicalName): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        return $this->themeRepository->search($criteria, $this->context)->getElements();
    }
}
