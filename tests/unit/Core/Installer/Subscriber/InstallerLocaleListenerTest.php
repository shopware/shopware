<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Subscriber\InstallerLocaleListener;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(InstallerLocaleListener::class)]
class InstallerLocaleListenerTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [RequestEvent::class],
            array_keys(InstallerLocaleListener::getSubscribedEvents())
        );
    }

    #[DataProvider('installerLocaleProvider')]
    public function testSetInstallerLocale(Request $request, string $expectedLocale): void
    {
        $listener = new InstallerLocaleListener(['de' => 'de-DE', 'en' => 'en-GB', 'nl' => 'nl-NL', 'fr' => 'fr-FR']);

        $listener->setInstallerLocale(
            new RequestEvent(
                $this->createMock(HttpKernelInterface::class),
                $request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );

        static::assertSame($expectedLocale, $request->attributes->get('_locale'));
        static::assertSame($expectedLocale, $request->getLocale());
    }

    public static function installerLocaleProvider(): \Generator
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        yield 'falls back to en if no locale can be found' => [
            $request,
            'en',
        ];

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->headers = new HeaderBag(['HTTP_ACCEPT_LANGUAGE' => 'es-ES']);

        yield 'falls back to en if browser header is not supported' => [
            $request,
            'en',
        ];

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->headers = new HeaderBag(['HTTP_ACCEPT_LANGUAGE' => 'de-DE']);

        yield 'uses browser header if it is supported with long iso code' => [
            $request,
            'de',
        ];

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->headers = new HeaderBag(['HTTP_ACCEPT_LANGUAGE' => 'de']);

        yield 'uses browser header if it is supported with short iso code' => [
            $request,
            'de',
        ];

        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set('language', 'es');
        $request->setSession($session);
        $request->headers = new HeaderBag(['HTTP_ACCEPT_LANGUAGE' => 'de']);

        yield 'falls back to browser header if session value is not supported' => [
            $request,
            'de',
        ];

        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set('language', 'nl');
        $request->setSession($session);
        $request->headers = new HeaderBag(['HTTP_ACCEPT_LANGUAGE' => 'de']);

        yield 'uses session value over browser header if it is supported' => [
            $request,
            'nl',
        ];

        $request = new Request(['language' => 'es']);
        $session = new Session(new MockArraySessionStorage());
        $session->set('language', 'nl');
        $request->setSession($session);
        $request->headers = new HeaderBag(['HTTP_ACCEPT_LANGUAGE' => 'de']);

        yield 'falls back to session value if query param is not supported' => [
            $request,
            'nl',
        ];

        $request = new Request(['language' => 'fr']);
        $session = new Session(new MockArraySessionStorage());
        $session->set('language', 'nl');
        $request->setSession($session);
        $request->headers = new HeaderBag(['HTTP_ACCEPT_LANGUAGE' => 'de']);

        yield 'uses query param over session value if it is supported' => [
            $request,
            'fr',
        ];
    }

    public function testItSavesLanguageChangeToSession(): void
    {
        $request = new Request(['language' => 'de']);
        $session = new Session(new MockArraySessionStorage());
        $session->set('language', 'en');
        $request->setSession($session);

        $listener = new InstallerLocaleListener(['de' => 'de-DE', 'en' => 'en-GB', 'nl' => 'nl-NL', 'fr' => 'fr-FR']);

        $listener->setInstallerLocale(
            new RequestEvent(
                $this->createMock(HttpKernelInterface::class),
                $request,
                HttpKernelInterface::MAIN_REQUEST
            )
        );

        static::assertSame('de', $request->attributes->get('_locale'));
        static::assertSame('de', $request->getLocale());
        static::assertSame('de', $session->get('language'));
    }
}
