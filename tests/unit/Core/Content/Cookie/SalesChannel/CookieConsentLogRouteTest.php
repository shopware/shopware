<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\Event\CookieConsentLoggedEvent;
use Shopware\Core\Content\Cookie\SalesChannel\AbstractCookieRoute;
use Shopware\Core\Content\Cookie\SalesChannel\CookieConsentLogRoute;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRouteResponse;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CookieConsentLogRoute::class)]
class CookieConsentLogRouteTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private CookieConsentLogRoute $route;

    protected function setUp(): void
    {
        $cookieGroup = new CookieGroup('cookie.groupRequired');
        $cookieGroup->isRequired = true;

        $cookieRoute = static::createStub(AbstractCookieRoute::class);
        $cookieRoute->method('getCookieGroups')
            ->willReturn(new CookieRouteResponse(new CookieGroupCollection([$cookieGroup]), 'current-hash', 'language-id'));

        $connection = static::createStub(Connection::class);
        $connection->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback($connection));

        $this->eventDispatcher = new CollectingEventDispatcher();

        $this->route = new CookieConsentLogRoute(
            $cookieRoute,
            $connection,
            $this->eventDispatcher,
            new MockClock('2026-07-13 12:00:00'),
        );
    }

    public function testItThrowsDecorationPatternException(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(CookieConsentLogRoute::class));

        $this->route->getDecorated();
    }

    public function testLogDispatchesEventAndReturnsNoContent(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $request = new Request(content: (string) json_encode([
            'consentAction' => 'accept_selected',
            'acceptedGroups' => ['cookie.groupRequired', 'cookie.groupStatistical'],
            'cookieConfigHash' => 'client-hash',
        ]));

        $response = $this->route->log($request, $salesChannelContext);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(1, $events);

        $event = $events[0];
        static::assertInstanceOf(CookieConsentLoggedEvent::class, $event);
        static::assertSame('accept_selected', $event->consentAction);
        static::assertSame(['cookie.groupRequired', 'cookie.groupStatistical'], $event->acceptedGroups);
        static::assertSame('client-hash', $event->configHash);
        static::assertSame($salesChannelContext->getSalesChannelId(), $event->salesChannelId);
        static::assertSame($salesChannelContext->getLanguageId(), $event->languageId);
    }

    public function testLogFallsBackToCurrentHashWhenClientSendsNone(): void
    {
        $request = new Request(content: (string) json_encode([
            'consentAction' => 'accept_all',
            'acceptedGroups' => ['cookie.groupRequired'],
        ]));

        $this->route->log($request, Generator::generateSalesChannelContext());

        $event = $this->eventDispatcher->getEvents()[0];
        static::assertInstanceOf(CookieConsentLoggedEvent::class, $event);
        static::assertSame('current-hash', $event->configHash);
    }

    public function testLogThrowsOnInvalidJsonBody(): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentLogPayload('body must be valid JSON'));

        $this->route->log(new Request(content: 'no-json{'), Generator::generateSalesChannelContext());
    }

    public function testLogThrowsWhenBodyIsNoObject(): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentLogPayload('body must be a JSON object'));

        $this->route->log(new Request(content: '"a-string"'), Generator::generateSalesChannelContext());
    }

    public function testLogThrowsOnUnknownConsentAction(): void
    {
        $request = new Request(content: (string) json_encode([
            'consentAction' => 'reject_all',
            'acceptedGroups' => [],
        ]));

        $this->expectExceptionObject(CookieException::invalidConsentLogPayload(
            'consentAction must be one of: accept_all, accept_required, accept_selected',
        ));

        $this->route->log($request, Generator::generateSalesChannelContext());
    }

    public function testLogThrowsWhenAcceptedGroupsIsMissing(): void
    {
        $request = new Request(content: (string) json_encode([
            'consentAction' => 'accept_all',
        ]));

        $this->expectExceptionObject(CookieException::invalidConsentLogPayload(
            'acceptedGroups must be a list with at most 100 entries',
        ));

        $this->route->log($request, Generator::generateSalesChannelContext());
    }

    public function testLogThrowsWhenAcceptedGroupsContainsNonStrings(): void
    {
        $request = new Request(content: (string) json_encode([
            'consentAction' => 'accept_all',
            'acceptedGroups' => ['cookie.groupRequired', 42],
        ]));

        $this->expectExceptionObject(CookieException::invalidConsentLogPayload(
            'acceptedGroups must contain non-empty strings',
        ));

        $this->route->log($request, Generator::generateSalesChannelContext());
    }

    public function testLogThrowsWhenConfigHashIsNoString(): void
    {
        $request = new Request(content: (string) json_encode([
            'consentAction' => 'accept_all',
            'acceptedGroups' => [],
            'cookieConfigHash' => ['not' => 'a-string'],
        ]));

        $this->expectExceptionObject(CookieException::invalidConsentLogPayload(
            'cookieConfigHash must be a non-empty string',
        ));

        $this->route->log($request, Generator::generateSalesChannelContext());
    }
}
