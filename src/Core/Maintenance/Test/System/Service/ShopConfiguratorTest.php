<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Test\System\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;
use Shopware\Core\Maintenance\System\Service\ShopConfigurator;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ShopConfiguratorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SystemConfigTestBehaviour;

    private ShopConfigurator $shopConfigurator;

    private SystemConfigService $systemConfigService;

    public function setUp(): void
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

        /** @var EntityRepositoryInterface $langRepo */
        $langRepo = $this->getContainer()->get('language.repository');

        $lang = $langRepo->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->first();

        static::assertEquals('Spanish', $lang->getName());
    }

    public function testSwitchLanguageWithDefaultLocale(): void
    {
        $this->shopConfigurator->setDefaultLanguage('en-GB');

        /** @var EntityRepositoryInterface $langRepo */
        $langRepo = $this->getContainer()->get('language.repository');

        $lang = $langRepo->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->first();

        static::assertEquals('English', $lang->getName());
    }

    public function testSwitchLanguageWithExistingLanguage(): void
    {
        $this->shopConfigurator->setDefaultLanguage('de-DE');

        /** @var EntityRepositoryInterface $langRepo */
        $langRepo = $this->getContainer()->get('language.repository');

        $lang = $langRepo->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->first();

        static::assertEquals('Deutsch', $lang->getName());
    }

    public function testSwitchDefaultCurrencyWithNewCurrency(): void
    {
        $this->shopConfigurator->setDefaultCurrency('RUB');

        /** @var EntityRepositoryInterface $langRepo */
        $langRepo = $this->getContainer()->get('currency.repository');

        /** @var CurrencyEntity $currency */
        $currency = $langRepo->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->first();

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

        /** @var EntityRepositoryInterface $langRepo */
        $langRepo = $this->getContainer()->get('currency.repository');

        /** @var CurrencyEntity $currency */
        $currency = $langRepo->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->first();

        static::assertEquals('Euro', $currency->getName());
    }

    public function testSwitchDefaultCurrencyWithExistingCurrency(): void
    {
        $this->shopConfigurator->setDefaultCurrency('GBP');

        /** @var EntityRepositoryInterface $langRepo */
        $langRepo = $this->getContainer()->get('currency.repository');

        /** @var CurrencyEntity $currency */
        $currency = $langRepo->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->first();

        static::assertEquals('Pound', $currency->getName());
        static::assertEquals(1, $currency->getFactor());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isoCode', 'EUR'));
        $oldDefault = $langRepo->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertEquals('Euro', $oldDefault->getName());
        static::assertEquals(1.1216169229561337, $oldDefault->getFactor());
    }
}
