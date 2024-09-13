<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Maintenance\SalesChannel\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelUpdateDomainCommand;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('core')]
class SalesChannelUpdateDomainCommandTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<SalesChannelDomainCollection>
     */
    private EntityRepository $domainRepo;

    protected function setUp(): void
    {
        $this->domainRepo = static::getContainer()->get('sales_channel_domain.repository');
        $criteria = $this->getStorefrontDomainCriteria();

        $domain = $this->domainRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if ($domain === null) {
            static::markTestSkipped('SalesChannelUpdateDomainCommandTests need storefront channel to be active');
        }
    }

    public function testUpdateDomainCommand(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(SalesChannelUpdateDomainCommand::class));
        $commandTester->execute(['domain' => 'test.de']);

        static::assertSame(
            Command::SUCCESS,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:update:domain\" returned errors:\n" . $commandTester->getDisplay()
        );

        $criteria = $this->getStorefrontDomainCriteria();

        $domain = $this->domainRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($domain);

        static::assertSame('test.de', parse_url($domain->getUrl(), \PHP_URL_HOST));
    }

    public function testUpdateWithRandomPreviousDomain(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(SalesChannelUpdateDomainCommand::class));
        $commandTester->execute(['domain' => 'test.de', '--previous-domain' => 'shop.test']);

        static::assertSame(
            Command::SUCCESS,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:update:domain\" returned errors:\n" . $commandTester->getDisplay()
        );

        $criteria = $this->getStorefrontDomainCriteria();

        $domain = $this->domainRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($domain);

        $defaultDomain = parse_url((string) EnvironmentHelper::getVariable('APP_URL'), \PHP_URL_HOST);
        static::assertSame($defaultDomain, parse_url($domain->getUrl(), \PHP_URL_HOST));
    }

    public function testUpdateWithCorrectPreviousDomain(): void
    {
        $defaultHost = parse_url((string) EnvironmentHelper::getVariable('APP_URL'), \PHP_URL_HOST);

        $commandTester = new CommandTester(static::getContainer()->get(SalesChannelUpdateDomainCommand::class));
        $commandTester->execute(['domain' => 'test.de', '--previous-domain' => $defaultHost]);

        static::assertSame(
            Command::SUCCESS,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:update:domain\" returned errors:\n" . $commandTester->getDisplay()
        );

        $criteria = $this->getStorefrontDomainCriteria();

        $domain = $this->domainRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($domain);

        static::assertSame('test.de', parse_url($domain->getUrl(), \PHP_URL_HOST));
    }

    private function getStorefrontDomainCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        return $criteria;
    }
}
