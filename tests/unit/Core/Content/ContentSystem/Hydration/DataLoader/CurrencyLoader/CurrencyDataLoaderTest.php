<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\CurrencyLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\CurrencyLoader\CurrencyDataLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\CurrencyLoader\CurrencyLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
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
    private AbstractCurrencyRoute&MockObject $currencyRoute;

    private CurrencyDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->currencyRoute = $this->createMock(AbstractCurrencyRoute::class);
        $this->dataLoader = new CurrencyDataLoader($this->currencyRoute);
    }

    #[TestDox('returns currency source type identifier')]
    public function testGetRequirementTypeReturnsCurrencyString(): void
    {
        static::assertSame('currency', CurrencyDataLoader::getRequirementType());
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

        $this->currencyRoute
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

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($currencies, $result->data);
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

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->expectExceptionMessage('The getDecorated() function of core class');

        $this->dataLoader->getDecorated();
    }
}
