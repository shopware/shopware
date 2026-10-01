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
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelReplaceUrlCommand;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelReplaceUrlCommandTest extends TestCase
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
            static::markTestSkipped('SalesChannelReplaceUrlCommandTests need storefront channel to be active');
        }
    }

    public function testUpdateUrlCommand(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(SalesChannelReplaceUrlCommand::class));
        $commandTester->execute([
            'previous-url' => EnvironmentHelper::getVariable('APP_URL'),
            'new-url' => 'https://www.new-url.com',
        ]);

        static::assertSame(
            Command::SUCCESS,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:replace:url\" returned errors:\n" . $commandTester->getDisplay()
        );

        $criteria = $this->getStorefrontDomainCriteria();

        $domain = $this->domainRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($domain);
        static::assertSame('https://www.new-url.com', $domain->getUrl());
    }

    public function testUpdateWithNonExistentPreviousUrl(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(SalesChannelReplaceUrlCommand::class));
        $commandTester->execute([
            'previous-url' => 'https://this-url-doesnt-exist.com',
            'new-url' => 'https://www.new-url.com',
        ]);

        static::assertSame(
            Command::FAILURE,
            $commandTester->getStatusCode(),
            '"bin/console sales-channel:replace:url" returned no errors.'
        );
        static::assertSame(
            '[ERROR] No sales channels found with URL https://this-url-doesnt-exist.com',
            trim($commandTester->getDisplay())
        );
    }

    public function testUpdateWithIncorrectNewUrl(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(SalesChannelReplaceUrlCommand::class));
        $commandTester->execute([
            'previous-url' => EnvironmentHelper::getVariable('APP_URL'),
            'new-url' => 'this-is-not-a-url',
        ]);

        static::assertSame(
            Command::FAILURE,
            $commandTester->getStatusCode(),
            '"bin/console sales-channel:replace:url" returned no errors.'
        );
        static::assertSame(
            '[ERROR] New URL: This value is not a valid URL.',
            trim($commandTester->getDisplay())
        );
    }

    public function testUpdateWithIdenticalUrls(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(SalesChannelReplaceUrlCommand::class));
        $commandTester->execute([
            'previous-url' => EnvironmentHelper::getVariable('APP_URL'),
            'new-url' => EnvironmentHelper::getVariable('APP_URL'),
        ]);

        static::assertSame(
            Command::FAILURE,
            $commandTester->getStatusCode(),
            '"bin/console sales-channel:replace:url" returned no errors.'
        );
        static::assertSame(
            trim($commandTester->getDisplay()),
            '[ERROR] New URL: This value should not be equal to "' . EnvironmentHelper::getVariable('APP_URL') . '".'
        );
    }

    private function getStorefrontDomainCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        return $criteria;
    }
}
