<?php

namespace Shopware\Tests\Bench;

use Doctrine\DBAL\Connection;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[BeforeMethods(['setup'])]
#[AfterMethods(['tearDown'])]
abstract class BenchCase
{
    protected IdsCollection $ids;

    protected SalesChannelContext $context;

    public function setup(): void
    {
        $this->ids = clone Fixtures::getIds();

        $this->context = clone Fixtures::context();

        $this->getContainer()->get(Connection::class)->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->getContainer()->get(Connection::class)->rollBack();
    }

    public function getContainer(): ContainerInterface
    {
        return KernelLifecycleManager::getKernel()->getContainer();
    }
}