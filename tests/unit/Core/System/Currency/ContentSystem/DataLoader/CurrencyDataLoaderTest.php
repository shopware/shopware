<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Currency\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\ContentSystem\DataLoader\CurrencyDataLoader;
use Shopware\Core\System\Currency\ContentSystem\DataLoader\CurrencyLoaderConfig;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\SalesChannel\AbstractCurrencyRoute;
use Shopware\Core\System\Currency\SalesChannel\CurrencyRouteResponse;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CurrencyDataLoader::class)]
class CurrencyDataLoaderTest extends TestCase
{
    private AbstractCurrencyRoute&Stub $currencyRoute;

    private CurrencyDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->currencyRoute = static::createStub(AbstractCurrencyRoute::class);
        $this->dataLoader = new CurrencyDataLoader($this->currencyRoute);
    }

    #[TestDox('returns currency source type identifier')]
    public function testGetRequirementTypeReturnsCurrencyString(): void
    {
        static::assertSame('currency', CurrencyDataLoader::getRequirementType());
    }

    #[TestDox('declares CurrencyCollection as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->dataLoader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(CurrencyCollection::class, $capabilities[0]->producedType);
    }

    #[TestDox('declares a single optional associations config key defaulting to an empty list')]
    public function testConfigSpecificationDeclaresOptionalAssociationsKey(): void
    {
        $specification = $this->dataLoader->configSpecification();

        static::assertCount(1, $specification->keys);
        $key = $specification->keys[0];
        static::assertSame('associations', $key->name);
        static::assertSame(ConfigKeyKind::Literal, $key->kind);
        static::assertSame('list<string>', $key->type);
        static::assertFalse($key->required);
        static::assertTrue($key->hasDefault);
        static::assertSame([], $key->default);
    }

    #[TestDox('loads currencies with default config and returns cachedExternally result')]
    public function testLoadWithDefaultConfig(): void
    {
        $currencies = new CurrencyCollection();
        $response = new CurrencyRouteResponse($currencies);
        $element = new ContentElement(id: 'element-id', component: 'test');
        $config = new CurrencyLoaderConfig();
        $requirement = new DataRequirement('currencyKey', 'currency', $config);
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $this->currencyRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $request);

        static::assertTrue($result->hasData());
        static::assertSame($currencies, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('adds associations from CurrencyLoaderConfig to criteria')]
    public function testLoadAddsAssociationsFromConfigToCriteria(): void
    {
        $currencies = new CurrencyCollection();
        $response = new CurrencyRouteResponse($currencies);
        $element = new ContentElement(id: 'element-id', component: 'test');
        $config = new CurrencyLoaderConfig(associations: ['country', 'translations']);
        $requirement = new DataRequirement('currencyKey', 'currency', $config);
        $context = Generator::generateSalesChannelContext();

        $currencyRoute = $this->createMock(AbstractCurrencyRoute::class);
        $currencyRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::anything(),
                static::anything(),
                static::callback(function (Criteria $criteria): bool {
                    static::assertContains('country', array_keys($criteria->getAssociations()));
                    static::assertContains('translations', array_keys($criteria->getAssociations()));

                    return true;
                })
            )
            ->willReturn($response);
        $dataLoader = new CurrencyDataLoader($currencyRoute);

        $dataLoader->load($element, $requirement, $context, new Request());
    }

    #[TestDox('loads currencies without associations when config is not a CurrencyLoaderConfig instance')]
    public function testLoadWithNonCurrencyLoaderConfigSkipsAssociations(): void
    {
        $currencies = new CurrencyCollection();
        $response = new CurrencyRouteResponse($currencies);
        $element = new ContentElement(id: 'element-id', component: 'test');
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('currencyKey', 'currency', $wrongConfig);
        $context = Generator::generateSalesChannelContext();

        $this->currencyRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($currencies, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }
}
