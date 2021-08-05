<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Csrf;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Csrf\CsrfPlaceholderHandler;
use Shopware\Storefront\Framework\Csrf\SessionProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;

class CsrfPlaceholderHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var LoaderInterface
     */
    private $originalLoader;

    /**
     * @var Environment
     */
    private $twig;

    protected function setUp(): void
    {
        $this->twig = $this->getContainer()->get('twig');
        $this->originalLoader = $this->twig->getLoader();

        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views/csrfTest');
        $this->twig->setLoader($loader);
    }

    protected function tearDown(): void
    {
        $this->twig->setLoader($this->originalLoader);
    }

    public function testCsrfReplacement(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->getContainer()->get('request_stack')->push($request);

        $csrfPlaceholderHandler = $this->createCsrfPlaceholderHandler();

        $response = new Response($this->getContentWithCsrfPLaceholder(), 200, ['Content-Type' => 'text/html']);

        $expectedContent = file_get_contents(__DIR__ . '/fixtures/Storefront/Resources/views/csrfTest/csrfTestRendered.html.twig');
        static::assertEquals(
            $expectedContent,
            $response->getContent()
        );

        $response = $csrfPlaceholderHandler->replaceCsrfToken($response, $request);

        static::assertStringNotContainsString('__token1__', $response->getContent());
        static::assertStringNotContainsString('__token2__', $response->getContent());
        static::assertStringNotContainsString('__token3__', $response->getContent());
    }

    public function testReplaceWithCsrfDisabledShouldNotReplace(): void
    {
        $csrfPlaceholderHandler = $this->createCsrfPlaceholderHandler(false);
        $expectedResponse = new Response($this->getContentWithCsrfPLaceholder(), 200, ['Content-Type' => 'text/html']);
        $response = $csrfPlaceholderHandler->replaceCsrfToken($expectedResponse, new Request());
        static::assertSame($expectedResponse, $response);
    }

    public function testReplaceWithAjaxModeShouldNotReplace(): void
    {
        $csrfPlaceholderHandler = $this->createCsrfPlaceholderHandler(true, 'ajax');
        $expectedResponse = new Response($this->getContentWithCsrfPLaceholder(), 200, ['Content-Type' => 'text/html']);
        $response = $csrfPlaceholderHandler->replaceCsrfToken($expectedResponse, new Request());
        static::assertSame($expectedResponse, $response);
    }

    public function testReplaceWithWrongContentTypeShouldNotReplace(): void
    {
        $csrfPlaceholderHandler = $this->createCsrfPlaceholderHandler();
        $expectedResponse = new Response($this->getContentWithCsrfPLaceholder(), 200, ['Content-Type' => 'text/javascript']);
        $response = $csrfPlaceholderHandler->replaceCsrfToken($expectedResponse, new Request());
        static::assertSame($expectedResponse, $response);
    }

    public function testReplaceWithOtherStatusCodeShouldNotReplace(): void
    {
        $csrfPlaceholderHandler = $this->createCsrfPlaceholderHandler();
        $expectedResponse = new Response($this->getContentWithCsrfPLaceholder(), 404, ['Content-Type' => 'text/html']);
        $response = $csrfPlaceholderHandler->replaceCsrfToken($expectedResponse, new Request());
        static::assertSame($expectedResponse, $response);
    }

    public function testReplaceStreamedResponseShouldNotCrash(): void
    {
        $csrfPlaceholderHandler = $this->createCsrfPlaceholderHandler();
        $expectedResponse = new StreamedResponse(function (): void {
        }, 200, ['Content-Type' => 'text/csv']);
        $response = $csrfPlaceholderHandler->replaceCsrfToken($expectedResponse, new Request());
        static::assertSame($expectedResponse, $response);
    }

    private function getContentWithCsrfPLaceholder(): string
    {
        $template = $this->twig->load('csrfTest.html.twig');

        return $template->render();
    }

    private function createCsrfPlaceholderHandler(bool $csrfEnabled = true, string $csrfMode = 'twig')
    {
        return new CsrfPlaceholderHandler(
            $this->getContainer()->get('security.csrf.token_manager'),
            $csrfEnabled,
            $csrfMode,
            $this->getContainer()->get('request_stack'),
            new SessionProvider($this->getContainer()->get('session'))
        );
    }

    private function generateToken(string $intent)
    {
        $tokenManager = $this->getContainer()->get('security.csrf.token_manager');

        return $tokenManager->getToken($intent)->getValue();
    }
}
