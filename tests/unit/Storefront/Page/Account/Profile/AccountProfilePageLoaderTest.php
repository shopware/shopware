<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Account\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
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
#[Package('checkout')]
#[CoversClass(AccountProfilePageLoader::class)]
class AccountProfilePageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private AccountProfilePageLoader $pageLoader;

    private AbstractTranslator&Stub $translator;

    private GenericPageLoader&Stub $genericPageLoader;

    private SalutationRoute&Stub $salutationRoute;

    private SalutationSorter&Stub $salutationSorter;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->salutationRoute = static::createStub(SalutationRoute::class);
        $this->salutationSorter = static::createStub(SalutationSorter::class);
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->genericPageLoader = static::createStub(GenericPageLoader::class);

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

        $salutationRoute = $this->createMock(SalutationRoute::class);
        $salutationRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($salutationResponse);

        $salutationSorter = $this->createMock(SalutationSorter::class);
        $salutationSorter
            ->expects($this->once())
            ->method('sort')
            ->willReturn($salutationsSorted);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');
        $genericPageLoader = $this->createMock(GenericPageLoader::class);
        $genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $translator = $this->createMock(AbstractTranslator::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');

        $pageLoader = new AccountProfilePageLoader(
            $genericPageLoader,
            $this->eventDispatcher,
            $salutationRoute,
            $salutationSorter,
            $translator
        );

        $salesChannelContext = $this->getContextWithDummyCustomer();
        $page = $pageLoader->load(new Request(), $salesChannelContext);

        static::assertSame($salutationsSorted, $page->getSalutations());
        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testshop', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(2, $events);

        static::assertInstanceOf(AccountProfilePageLoadedEvent::class, $events[1]);
        static::assertInstanceOf(SalutationRouteRequestEvent::class, $events[0]);
    }

    public function testSetStandardMetaData(): void
    {
        $pageLoader = new TestAccountProfilePageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->salutationRoute,
            $this->salutationSorter,
            $this->translator,
        );

        $page = new AccountProfilePage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }

    public function testNoCustomerException(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        static::expectException(CustomerNotLoggedInException::class);

        $this->pageLoader->load(new Request(), $salesChannelContext);
    }

    private function getContextWithDummyCustomer(): SalesChannelContext
    {
        $customer = new CustomerEntity();

        $context = static::createStub(SalesChannelContext::class);
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
