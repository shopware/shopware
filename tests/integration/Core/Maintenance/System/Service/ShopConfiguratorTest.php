<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Maintenance\System\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
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
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private ShopConfigurator $shopConfigurator;

    private SystemConfigService $systemConfigService;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $langRepo;

    /**
     * @var EntityRepository<CurrencyCollection>
     */
    private EntityRepository $currencyRepo;

    protected function setUp(): void
    {
        $this->shopConfigurator = static::getContainer()->get(ShopConfigurator::class);
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $this->langRepo = static::getContainer()->get('language.repository');
        $this->currencyRepo = static::getContainer()->get('currency.repository');
    }

    public function testUpdateBasicInformation(): void
    {
        $this->shopConfigurator->updateBasicInformation('test-shop', 'shop@test.com');

        static::assertSame('test-shop', $this->systemConfigService->get('core.basicInformation.shopName'));
        static::assertSame('shop@test.com', $this->systemConfigService->get('core.basicInformation.email'));
    }

    public function testSwitchLanguageWithNewLanguage(): void
    {
        $this->shopConfigurator->setDefaultLanguage('es-ES');

        $lang = $this->langRepo->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($lang);
        static::assertSame('Spanish', $lang->getName());
    }

    public function testSwitchLanguageWithDefaultLocale(): void
    {
        $this->shopConfigurator->setDefaultLanguage('en-GB');

        $lang = $this->langRepo->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($lang);
        static::assertSame('English', $lang->getName());
    }

    public function testSwitchLanguageWithExistingLanguage(): void
    {
        $this->shopConfigurator->setDefaultLanguage('de-DE');

        $lang = $this->langRepo->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($lang);
        static::assertSame('Deutsch', $lang->getName());
    }

    public function testSwitchDefaultCurrencyWithNewCurrency(): void
    {
        $this->shopConfigurator->setDefaultCurrency('RUB');

        $currency = $this->currencyRepo->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($currency);
        static::assertSame('RUB', $currency->getSymbol());
        static::assertSame('Russian Ruble', $currency->getName());
        static::assertSame('RUB', $currency->getShortName());
        static::assertSame('RUB', $currency->getIsoCode());
        static::assertSame(1.0, $currency->getFactor());
        static::assertSame(2, $currency->getItemRounding()->getDecimals());
        static::assertSame(0.01, $currency->getItemRounding()->getInterval());
        static::assertTrue($currency->getItemRounding()->roundForNet());
        static::assertSame(2, $currency->getTotalRounding()->getDecimals());
        static::assertSame(0.01, $currency->getTotalRounding()->getInterval());
        static::assertTrue($currency->getTotalRounding()->roundForNet());
    }

    public function testSwitchDefaultCurrencyWithDefaultCurrency(): void
    {
        $this->shopConfigurator->setDefaultCurrency('EUR');

        $currency = $this->currencyRepo->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($currency);
        static::assertSame('Euro', $currency->getName());
    }

    public function testSwitchDefaultCurrencyWithExistingCurrency(): void
    {
        $this->shopConfigurator->setDefaultCurrency('GBP');

        $currency = $this->currencyRepo->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($currency);
        static::assertSame('Pound', $currency->getName());
        static::assertSame(1.0, $currency->getFactor());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isoCode', 'EUR'));

        $oldDefault = $this->currencyRepo->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($oldDefault);
        static::assertSame('Euro', $oldDefault->getName());
        static::assertSame(1.1216169229561337, $oldDefault->getFactor());
    }
}
