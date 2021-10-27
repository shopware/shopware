<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Script;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Script\AppScriptCollection;
use Shopware\Core\Framework\App\Script\ScriptStateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\App\StorefrontPluginRegistryTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ScriptStateServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    use StorefrontPluginRegistryTestBehaviour;

    private EntityRepository $scriptRepo;

    private ScriptStateService $scriptStateService;

    private EntityRepository $appRepo;

    public function setUp(): void
    {
        $this->scriptRepo = $this->getContainer()->get('app_script.repository');
        $this->appRepo = $this->getContainer()->get('app.repository');
        $this->scriptStateService = $this->getContainer()->get(ScriptStateService::class);
    }

    public function testActivateApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test', false);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'test'));

        $appId = $this->appRepo->searchIds($criteria, Context::createDefaultContext())->firstId();

        $this->scriptStateService->activateAppScripts($appId, Context::createDefaultContext());

        $activeTemplates = $this->fetchActiveScripts($appId);
        static::assertCount(1, $activeTemplates);
    }

    public function testDeactivateApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'test'));

        $appId = $this->appRepo->searchIds($criteria, Context::createDefaultContext())->firstId();

        $this->scriptStateService->deactivateAppScripts($appId, Context::createDefaultContext());

        $activeTemplates = $this->fetchActiveScripts($appId);
        static::assertEmpty($activeTemplates);
    }

    private function fetchActiveScripts(string $appId): AppScriptCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        return $this->scriptRepo->search($criteria, Context::createDefaultContext())->getEntities();
    }
}
