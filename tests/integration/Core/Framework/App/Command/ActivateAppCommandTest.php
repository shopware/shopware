<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Command\ActivateAppCommand;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class ActivateAppCommandTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    protected function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
    }

    public function testActivateApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/withoutPermissions', false);
        $appName = 'withoutPermissions';

        $commandTester = new CommandTester($this->getContainer()->get(ActivateAppCommand::class));

        $commandTester->execute(['name' => $appName]);

        static::assertSame(0, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] App activated successfully.', $commandTester->getDisplay());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));

        $app = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($app);

        static::assertTrue($app->isActive());
    }

    public function testActivateNonExistingAppFails(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(ActivateAppCommand::class));

        $appName = 'NonExisting';
        $commandTester->execute(['name' => $appName]);

        static::assertSame(1, $commandTester->getStatusCode());

        static::assertStringContainsString("[ERROR] No app found for \"$appName\".", $commandTester->getDisplay());
    }
}
