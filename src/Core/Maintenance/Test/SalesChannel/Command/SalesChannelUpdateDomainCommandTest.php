<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Test\SalesChannel\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelUpdateDomainCommand;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('core')]
class SalesChannelUpdateDomainCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testUpdateDomainCommand(): void
    {
        $this->createSalesChannel(['domains' => [
            [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost',
            ],
        ]]);

        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelUpdateDomainCommand::class));
        $commandTester->execute(['domain' => 'test.de']);

        static::assertEquals(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:maintenance:disable\" returned errors:\n" . $commandTester->getDisplay()
        );

        /** @var EntityRepository $domainRepo */
        $domainRepo = $this->getContainer()->get('sales_channel_domain.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $domains = $domainRepo->search($criteria, Context::createDefaultContext());
        /** @var SalesChannelDomainEntity $firstDomain */
        $firstDomain = $domains->first();
        /** @var SalesChannelDomainEntity $lastDomain */
        $lastDomain = $domains->last();

        static::assertSame('test.de', parse_url($firstDomain->getUrl(), \PHP_URL_HOST));
        static::assertSame('test.de', parse_url($lastDomain->getUrl(), \PHP_URL_HOST));
    }

    public function testUpdateWithRandomPreviousDomain(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelUpdateDomainCommand::class));
        $commandTester->execute(['domain' => 'test.de', '--previous-domain' => 'shop.test']);

        static::assertEquals(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:maintenance:disable\" returned errors:\n" . $commandTester->getDisplay()
        );

        $domainRepo = $this->getContainer()->get('sales_channel_domain.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        /** @var SalesChannelDomainEntity $domain */
        $domain = $domainRepo->search($criteria, Context::createDefaultContext())->first();

        $defaultDomain = parse_url((string) EnvironmentHelper::getVariable('APP_URL'), \PHP_URL_HOST);
        static::assertSame($defaultDomain, parse_url($domain->getUrl(), \PHP_URL_HOST));
    }

    public function testUpdateWithCorrectPreviousDomain(): void
    {
        $defaultHost = parse_url((string) EnvironmentHelper::getVariable('APP_URL'), \PHP_URL_HOST);

        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelUpdateDomainCommand::class));
        $commandTester->execute(['domain' => 'test.de', '--previous-domain' => $defaultHost]);

        static::assertEquals(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:maintenance:disable\" returned errors:\n" . $commandTester->getDisplay()
        );

        $domainRepo = $this->getContainer()->get('sales_channel_domain.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        /** @var SalesChannelDomainEntity $domain */
        $domain = $domainRepo->search($criteria, Context::createDefaultContext())->first();

        static::assertSame('test.de', parse_url($domain->getUrl(), \PHP_URL_HOST));
    }

    public function testUpdateWithSpecificSalesChannel(): void
    {
        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $context = Context::createDefaultContext();

        $secondSalesChannel = $this->createSalesChannel();

        $storefrontCriteria = (new Criteria())->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $salesChannelId = $salesChannelRepository->searchIds($storefrontCriteria, $context)->firstId();

        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelUpdateDomainCommand::class));
        $commandTester->execute(['domain' => 'test.de', '--sales-channel-id' => $salesChannelId]);

        static::assertEquals(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:maintenance:disable\" returned errors:\n" . $commandTester->getDisplay()
        );

        /** @var EntityRepository $domainRepo */
        $domainRepo = $this->getContainer()->get('sales_channel_domain.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $domains = $domainRepo->search($criteria, Context::createDefaultContext());

        /** @var SalesChannelDomainEntity $firstDomain */
        $firstDomain = $domains->first();
        /** @var SalesChannelDomainEntity $lastDomain */
        $lastDomain = $domains->last();

        static::assertSame('test.de', parse_url($firstDomain->getUrl(), \PHP_URL_HOST));
        static::assertSame(parse_url($secondSalesChannel['domains'][0]['url'], \PHP_URL_HOST), parse_url($lastDomain->getUrl(), \PHP_URL_HOST));
    }

    public function testUpdateWithPortComponent(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelUpdateDomainCommand::class));
        $commandTester->execute(['domain' => 'test.de:9999']);

        static::assertEquals(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:maintenance:disable\" returned errors:\n" . $commandTester->getDisplay()
        );

        $domainRepo = $this->getContainer()->get('sales_channel_domain.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        /** @var SalesChannelDomainEntity $domain */
        $domain = $domainRepo->search($criteria, Context::createDefaultContext())->first();

        static::assertSame('test.de', parse_url($domain->getUrl(), \PHP_URL_HOST));
        static::assertSame(9999, parse_url($domain->getUrl(), \PHP_URL_PORT));
    }
}
