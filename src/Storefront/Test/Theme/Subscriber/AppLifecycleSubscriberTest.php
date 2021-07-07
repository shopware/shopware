<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class AppLifecycleSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AppLifecycle $appLifecycle;

    private EntityRepositoryInterface $appRepository;

    private Context $context;

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');

        $userRepository = $this->getContainer()->get('user.repository');
        $userId = $userRepository->searchIds(new Criteria(), Context::createDefaultContext())->firstId();
        $source = new AdminApiSource($userId);
        $source->setIsAdmin(true);

        $this->appLifecycle = $this->getContainer()->get(AppLifecycle::class);
        $this->context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
    }

    /**
     * @dataProvider themeProvideData
     */
    public function testThemeRemovalOnDelete(bool $keepUserData): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../fixtures/Apps/theme/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $apps);

        $app = [
            'id' => $apps->first()->getId(),
            'name' => $apps->first()->getName(),
            'roleId' => $apps->first()->getAclRoleId(),
        ];

        $themeRepo = $this->getContainer()->get('theme.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $app['name']));

        static::assertCount(1, $themeRepo->search($criteria, $this->context)->getElements());

        $this->appLifecycle->delete($app['name'], $app, $this->context, $keepUserData);
        static::assertCount($keepUserData ? 1 : 0, $themeRepo->search($criteria, $this->context)->getElements());

        $apps = $this->appRepository->searchIds(new Criteria(), $this->context)->getIds();
        static::assertCount(0, $apps);
    }

    public function themeProvideData(): array
    {
        return [
            'Test with keep data' => [true],
            'Test without keep data' => [false],
        ];
    }
}
