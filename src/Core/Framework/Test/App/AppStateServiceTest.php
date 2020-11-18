<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AppStateServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    use StorefrontPluginRegistryTestBehaviour;

    /**
     * @var ThemeService
     */
    private $themeService;

    /**
     * @var EntityRepository
     */
    private $appRepo;

    /**
     * @var EntityRepository
     */
    private $themeRepo;

    /**
     * @var AppStateService
     */
    private $appStateService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function setUp(): void
    {
        $this->themeService = $this->getContainer()->get(ThemeService::class);
        $this->appRepo = $this->getContainer()->get('app.repository');
        $this->themeRepo = $this->getContainer()->get('theme.repository');
        $this->appStateService = $this->getContainer()->get(AppStateService::class);
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
    }

    public function testNotFoundAppThrowsOnActivate(): void
    {
        static::expectException(AppNotFoundException::class);
        $this->appStateService->activateApp(Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testNotFoundAppThrowsOnDeactivate(): void
    {
        static::expectException(AppNotFoundException::class);
        $this->appStateService->deactivateApp(Uuid::randomHex(), Context::createDefaultContext());
    }
}
