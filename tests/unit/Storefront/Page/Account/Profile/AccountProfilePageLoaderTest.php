<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Account\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRoute;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRouteResponse;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\System\Salutation\SalutationSorter;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Event\RouteRequest\SalutationRouteRequestEvent;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePage;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AccountProfilePageLoader::class)]
class AccountProfilePageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private AccountProfilePageLoader $pageLoader;

    private AbstractTranslator&MockObject $translator;

    private GenericPageLoader&MockObject $genericPageLoader;

    private SalutationRoute&MockObject $salutationRoute;

    private SalutationSorter&MockObject $salutationSorter;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->salutationRoute = $this->createMock(SalutationRoute::class);
        $this->salutationSorter = $this->createMock(SalutationSorter::class);
        $this->translator = $this->createMock(AbstractTranslator::class);
        $this->genericPageLoader = $this->createMock(GenericPageLoader::class);

        $this->pageLoader = new AccountProfilePageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->salutationRoute,
            $this->salutationSorter,
            $this->translator
        );
    }

    public function testLoad(): void
    {
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
        $this->genericPageLoader
            ->expects(static::once())
            ->method('load')
            ->willReturn($page);

        $this->translator
            ->expects(static::once())
            ->method('trans')
            ->willReturn('translated');

        $salesChannelContext = $this->getContextWithDummyCustomer();
        $page = $this->pageLoader->load(new Request(), $salesChannelContext);

        static::assertSame($salutationsSorted, $page->getSalutations());
        static::assertEquals('translated | testshop', $page->getMetaInformation()?->getMetaTitle());
        static::assertEquals('noindex,follow', $page->getMetaInformation()?->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(2, $events);

        static::assertInstanceOf(AccountProfilePageLoadedEvent::class, $events[1]);
        static::assertInstanceOf(SalutationRouteRequestEvent::class, $events[0]);
    }

    public function testSetStandardMetaDataIfTranslatorIsSet(): void
    {
        $pageLoader = new TestAccountProfilePageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->salutationRoute,
            $this->salutationSorter,
            $this->translator
        );

        $page = new AccountProfilePage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }

    public function testNotSetStandardMetaDataIfTranslatorIsNotSet(): void
    {
        $pageLoader = new TestAccountProfilePageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->salutationRoute,
            $this->salutationSorter,
            null
        );

        $page = new AccountProfilePage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertNull($page->getMetaInformation());
    }

    public function testNoCustomerException(): void
    {
        $pageLoader = new AccountProfilePageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->salutationRoute,
            $this->salutationSorter,
            null
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        static::expectException(CustomerNotLoggedInException::class);

        $this->pageLoader->load(new Request(), $salesChannelContext);
    }

    private function getContextWithDummyCustomer(): SalesChannelContext
    {
        $customer = new CustomerEntity();

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getCustomer')
            ->willReturn($customer);

        return $context;
    }
}

/**
 * @internal
 */
class TestAccountProfilePageLoader extends AccountProfilePageLoader
{
    public function setMetaInformationAccess(AccountProfilePage $page): void
    {
        self::setMetaInformation($page);
    }
}
