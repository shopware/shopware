<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Controller\TranslationController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(TranslationController::class)]
class TranslationControllerTest extends TestCase
{
    use InstallerControllerTestTrait;

    private TranslationController $controller;

    private MockObject&Environment $twig;

    protected function setUp(): void
    {
        $this->twig = $this->createMock(Environment::class);

        $this->controller = new TranslationController();
        $this->controller->setContainer($this->getInstallerContainer($this->twig));
    }

    public function testTranslationsRouteWithSelectedLanguages(): void
    {
        $this->twig->expects($this->once())->method('render')
            ->with(
                '@Installer/installer/translation.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'locales' => ['en-GB', 'de-DE'],
                    'total' => 2,
                    'supportedLanguages' => [],
                ])
            )
            ->willReturn('translation page');

        $session = new Session(new MockArraySessionStorage());
        $session->set('SELECTED_LANGUAGES', ['en-GB', 'de-DE']);
        $request = Request::create('/installer/translation');
        $request->setSession($session);

        $response = $this->controller->translations($request);
        static::assertSame('translation page', $response->getContent());
    }

    public function testTranslationsRouteWithInstallerLocales(): void
    {
        $this->twig->expects($this->once())->method('render')
            ->with(
                '@Installer/installer/translation.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'locales' => ['ro-RO', 'en-GB'],
                    'total' => 2,
                    'supportedLanguages' => [],
                ])
            )
            ->willReturn('translation page');

        $session = new Session(new MockArraySessionStorage());
        $session->set('installer.locales', ['ro-RO', 'en-GB']);
        $request = Request::create('/installer/translation');
        $request->setSession($session);

        $response = $this->controller->translations($request);
        static::assertSame('translation page', $response->getContent());
    }

    public function testTranslationsRouteWithEmptyLocales(): void
    {
        $this->twig->expects($this->once())->method('render')
            ->with(
                '@Installer/installer/translation.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'locales' => [],
                    'total' => 0,
                    'supportedLanguages' => [],
                ])
            )
            ->willReturn('translation page');

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/installer/translation');
        $request->setSession($session);

        $response = $this->controller->translations($request);
        static::assertSame('translation page', $response->getContent());
    }

    public function testRunWithSuccessfulTranslation(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('SELECTED_LANGUAGES', ['en-GB', 'de-DE']);
        $request = Request::create('/installer/translation/run', 'POST');
        $request->setSession($session);

        $response = $this->controller->run($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        $decodedContent = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decodedContent);

        static::assertArrayHasKey('isFinished', $decodedContent);
        static::assertArrayHasKey('skipped', $decodedContent);
        static::assertArrayHasKey('failures', $decodedContent);

        static::assertIsBool($decodedContent['isFinished']);
        static::assertIsBool($decodedContent['skipped']);
        static::assertIsArray($decodedContent['failures']);
    }

    public function testRunClearsOldFailures(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('SELECTED_LANGUAGES', ['en-GB']);
        $session->set('TRANSLATION_FAILED', [['error' => 'old error']]);
        $request = Request::create('/installer/translation/run', 'POST');
        $request->setSession($session);

        static::assertTrue($session->has('TRANSLATION_FAILED'));

        $response = $this->controller->run($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRunResponseStructure(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('SELECTED_LANGUAGES', ['en-GB']);
        $request = Request::create('/installer/translation/run', 'POST');
        $request->setSession($session);

        $response = $this->controller->run($request);

        $content = $response->getContent();
        static::assertIsString($content);
        $decodedContent = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decodedContent);

        static::assertArrayHasKey('isFinished', $decodedContent);
        static::assertArrayHasKey('skipped', $decodedContent);
        static::assertArrayHasKey('failures', $decodedContent);

        static::assertIsBool($decodedContent['isFinished']);
        static::assertIsBool($decodedContent['skipped']);
        static::assertIsArray($decodedContent['failures']);
    }

    public function testRunSessionHandling(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('SELECTED_LANGUAGES', ['en-GB', 'de-DE', 'fr-FR']);
        $request = Request::create('/installer/translation/run', 'POST');
        $request->setSession($session);

        $response = $this->controller->run($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        static::assertTrue($session->has('SELECTED_LANGUAGES'));
        static::assertSame(['en-GB', 'de-DE', 'fr-FR'], $session->get('SELECTED_LANGUAGES'));
    }
}
