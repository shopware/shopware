<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Maintenance\System\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Maintenance\System\Service\ShopConfigurator;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('services-settings')]
class ShopConfiguratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ShopConfigurator $shopConfigurator;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->shopConfigurator = $this->getContainer()->get(ShopConfigurator::class);
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
    }

    public function testUpdateBasicInformation(): void
    {
        $this->shopConfigurator->updateBasicInformation('test-shop', 'shop@test.com');

        static::assertEquals('test-shop', $this->systemConfigService->get('core.basicInformation.shopName'));
        static::assertEquals('shop@test.com', $this->systemConfigService->get('core.basicInformation.email'));
    }

    public function testSwitchLanguageWithNewLanguage(): void
    {
        $this->shopConfigurator->setDefaultLanguage('es-ES');

        /** @var EntityRepository<LanguageCollection> $langRepo */
        $langRepo = $this->getContainer()->get('language.repository');

        $lang = $langRepo->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($lang);
        static::assertEquals('Spanish', $lang->getName());
    }

    public function testSwitchLanguageWithDefaultLocale(): void
    {
        $this->shopConfigurator->setDefaultLanguage('en-GB');

        /** @var EntityRepository<LanguageCollection> $langRepo */
        $langRepo = $this->getContainer()->get('language.repository');

        $lang = $langRepo->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($lang);
        static::assertEquals('English', $lang->getName());
    }

    public function testSwitchLanguageWithExistingLanguage(): void
    {
        $this->shopConfigurator->setDefaultLanguage('de-DE');

        /** @var EntityRepository<LanguageCollection> $langRepo */
        $langRepo = $this->getContainer()->get('language.repository');

        $lang = $langRepo->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($lang);
        static::assertEquals('Deutsch', $lang->getName());
    }

    public function testSwitchDefaultCurrencyWithNewCurrency(): void
    {
        $this->shopConfigurator->setDefaultCurrency('RUB');

        /** @var EntityRepository<CurrencyCollection> $langRepo */
        $langRepo = $this->getContainer()->get('currency.repository');

        $currency = $langRepo->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($currency);
        static::assertEquals('RUB', $currency->getSymbol());
        static::assertEquals('Russian Ruble', $currency->getName());
        static::assertEquals('RUB', $currency->getShortName());
        static::assertEquals('RUB', $currency->getIsoCode());
        static::assertEquals(1, $currency->getFactor());
        static::assertEquals(2, $currency->getItemRounding()->getDecimals());
        static::assertEquals(0.01, $currency->getItemRounding()->getInterval());
        static::assertTrue($currency->getItemRounding()->roundForNet());
        static::assertEquals(2, $currency->getTotalRounding()->getDecimals());
        static::assertEquals(0.01, $currency->getTotalRounding()->getInterval());
        static::assertTrue($currency->getTotalRounding()->roundForNet());
    }

    public function testSwitchDefaultCurrencyWithDefaultCurrency(): void
    {
        $this->shopConfigurator->setDefaultCurrency('EUR');

        /** @var EntityRepository<CurrencyCollection> $langRepo */
        $langRepo = $this->getContainer()->get('currency.repository');

        $currency = $langRepo->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($currency);
        static::assertEquals('Euro', $currency->getName());
    }

    public function testSwitchDefaultCurrencyWithExistingCurrency(): void
    {
        $this->shopConfigurator->setDefaultCurrency('GBP');

        /** @var EntityRepository<CurrencyCollection> $langRepo */
        $langRepo = $this->getContainer()->get('currency.repository');

        $currency = $langRepo->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($currency);
        static::assertEquals('Pound', $currency->getName());
        static::assertEquals(1, $currency->getFactor());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isoCode', 'EUR'));

        $oldDefault = $langRepo->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($oldDefault);
        static::assertEquals('Euro', $oldDefault->getName());
        static::assertEquals(1.1216169229561337, $oldDefault->getFactor());
    }
}
