<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Account\Login;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Core\System\Country\SalesChannel\CountryRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRoute;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRouteResponse;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\System\Salutation\SalutationSorter;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Page\Account\Login\AccountLoginPage;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoadedEvent;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AccountLoginPageLoader::class)]
class AccountLoginPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private CountryRoute&MockObject $countryRoute;

    private AccountLoginPageLoader $pageLoader;

    private SalutationRoute&MockObject $salutationRoute;

    private SalutationSorter&MockObject $salutationSorter;

    private AbstractTranslator&MockObject $translator;

    private GenericPageLoader&MockObject $genericLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();

        $this->countryRoute = $this->createMock(CountryRoute::class);
        $this->salutationRoute = $this->createMock(SalutationRoute::class);
        $this->salutationSorter = $this->createMock(SalutationSorter::class);
        $this->translator = $this->createMock(AbstractTranslator::class);
        $this->genericLoader = $this->createMock(GenericPageLoader::class);

        $this->pageLoader = new AccountLoginPageLoader(
            $this->genericLoader,
            $this->eventDispatcher,
            $this->countryRoute,
            $this->salutationRoute,
            $this->salutationSorter,
            $this->translator
        );
    }

    public function testLoad(): void
    {
        $country = new CountryEntity();
        $country->assign(
            [
                'id' => Uuid::randomHex(),
                'name' => 'lalaland',
            ]
        );
        $country->setUniqueIdentifier(Uuid::randomHex());
        $countries = new CountryCollection([$country]);
        $countryResponse = new CountryRouteResponse(
            new EntitySearchResult(
                CountryDefinition::ENTITY_NAME,
                1,
                $countries,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $this->countryRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn($countryResponse);

        $salutation = new SalutationEntity();
        $salutation->setId(Uuid::randomHex());

        $salutation2Id = Uuid::randomHex();
        $salutation2 = new SalutationEntity();
        $salutation2->setId($salutation2Id);

        $salutations = new SalutationCollection([$salutation, $salutation2]);
        $salutationResponse = new SalutationRouteResponse(
            new EntitySearchResult(
                SalutationDefinition::ENTITY_NAME,
                2,
                $salutations,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $salutationsSorted = new SalutationCollection([$salutation2, $salutation]);

        $this->salutationRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn($salutationResponse);

        $this->salutationSorter
            ->expects(static::once())
            ->method('sort')
            ->willReturn($salutationsSorted);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');
        $this->genericLoader
            ->expects(static::once())
            ->method('load')
            ->willReturn($page);

        $this->translator
            ->expects(static::once())
            ->method('trans')
            ->willReturn('translated');

        $page = $this->pageLoader->load(new Request(), $this->createMock(SalesChannelContext::class));

        static::assertEquals($countries, $page->getCountries());
        static::assertSame($salutationsSorted, $page->getSalutations());
        static::assertEquals('translated | testshop', $page->getMetaInformation()?->getMetaTitle());
        static::assertEquals('noindex,follow', $page->getMetaInformation()?->getRobots());
        $events = $this->eventDispatcher->getEvents();

        static::assertCount(1, $events);
        static::assertInstanceOf(AccountLoginPageLoadedEvent::class, $events[0]);
    }

    public function testSetStandardMetaDataIfTranslatorIsSet(): void
    {
        $pageLoader = new TestAccountLoginPageLoader(
            $this->genericLoader,
            $this->eventDispatcher,
            $this->countryRoute,
            $this->salutationRoute,
            $this->salutationSorter,
            $this->translator
        );

        $page = new AccountLoginPage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }

    public function testNotSetStandardMetaDataIfTranslatorIsNotSet(): void
    {
        $pageLoader = new TestAccountLoginPageLoader(
            $this->genericLoader,
            $this->eventDispatcher,
            $this->countryRoute,
            $this->salutationRoute,
            $this->salutationSorter,
            null
        );

        $page = new AccountLoginPage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertNull($page->getMetaInformation());
    }
}

/**
 * @internal
 */
class TestAccountLoginPageLoader extends AccountLoginPageLoader
{
    public function setMetaInformationAccess(AccountLoginPage $page): void
    {
        self::setMetaInformation($page);
    }
}
