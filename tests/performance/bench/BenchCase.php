<?php declare(strict_types=1);

namespace Shopware\Tests\Bench;

use Doctrine\DBAL\Connection;
use PhpBench\Attributes\Groups;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal - only for performance benchmarks
 */
#[Groups(['base'])]
abstract class BenchCase
{
    protected IdsCollection $ids;

    protected SalesChannelContext $context;

    public function setup(): void
    {
        $this->ids = clone Fixtures::getIds();

        $this->context = clone Fixtures::context();

        $this->getContainer()->get(Connection::class)->setNestTransactionsWithSavepoints(true);
        $this->getContainer()->get(Connection::class)->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->getContainer()->get(Connection::class)->rollBack();
    }

    public function getContainer(): ContainerInterface
    {
        $container = KernelLifecycleManager::getKernel()->getContainer();

        if (!$container->has('test.service_container')) {
            throw new \RuntimeException('Unable to run tests against kernel without test.service_container');
        }

        /** @var ContainerInterface $testContainer */
        $testContainer = $container->get('test.service_container');

        return $testContainer;
    }
}
