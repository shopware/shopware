<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Csrf;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Csrf\CsrfReplaceHelper;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class CsrfReplaceHelperTest extends TestCase
{
    use IntegrationTestBehaviour;

    private $originalLoader;

    /**
     * @var Environment
     */
    private $twig;

    protected function setUp(): void
    {
        $this->twig = $this->getContainer()->get('twig');
        $this->originalLoader = $this->twig->getLoader();

        $loader = new FilesystemLoader(__DIR__ . '/../../fixtures/Storefront/Resources/views/csrfTest');
        $this->twig->setLoader($loader);
    }

    protected function tearDown(): void
    {
        $this->twig->setLoader($this->originalLoader);
    }

    public function testCsrfReplacement(): void
    {
        $csrfReplaceHelper = $this->createCsrfReplaceHelper();

        $response = new Response($this->getContentWithCsrfPLaceholder(), 200, ['Content-Type' => 'text/html']);

        $expectedContent = file_get_contents(__DIR__ . '/../../fixtures/Storefront/Resources/views/csrfTest/csrfTestRendered.html.twig');
        static::assertEquals(
            $expectedContent,
            $response->getContent()
        );

        $response = $csrfReplaceHelper->replaceCsrfToken($response);

        $expectedContent = file_get_contents(__DIR__ . '/../../fixtures/Storefront/Resources/views/csrfTest/csrfTestReplaced.html.twig');
        $expectedContent = preg_replace(
            ['/__token1__/', '/__token2__/', '/__token3__/'],
            [$this->generateToken('token1'), $this->generateToken('token2'), $this->generateToken('token3')],
            $expectedContent
        );

        static::assertEquals(
            $expectedContent,
            $response->getContent()
        );
    }

    public function testReplaceWithCsrfDisabledShouldNotReplace(): void
    {
        $csrfReplaceHelper = $this->createCsrfReplaceHelper(false);
        $expectedResponse = new Response($this->getContentWithCsrfPLaceholder(), 200, ['Content-Type' => 'text/html']);
        $response = $csrfReplaceHelper->replaceCsrfToken($expectedResponse);
        static::assertSame($expectedResponse, $response);
    }

    public function testReplaceWithAjaxModeShouldNotReplace(): void
    {
        $csrfReplaceHelper = $this->createCsrfReplaceHelper(true, 'ajax');
        $expectedResponse = new Response($this->getContentWithCsrfPLaceholder(), 200, ['Content-Type' => 'text/javascript']);
        $response = $csrfReplaceHelper->replaceCsrfToken($expectedResponse);
        static::assertSame($expectedResponse, $response);
    }

    public function testReplaceWithWrongContentTypeShouldNotReplace(): void
    {
        $csrfReplaceHelper = $this->createCsrfReplaceHelper();
        $expectedResponse = new Response($this->getContentWithCsrfPLaceholder(), 200, ['Content-Type' => 'text/javascript']);
        $response = $csrfReplaceHelper->replaceCsrfToken($expectedResponse);
        static::assertSame($expectedResponse, $response);
    }

    public function testReplaceWithOtherStatusCodeShouldNotReplace(): void
    {
        $csrfReplaceHelper = $this->createCsrfReplaceHelper();
        $expectedResponse = new Response($this->getContentWithCsrfPLaceholder(), 404, ['Content-Type' => 'text/html']);
        $response = $csrfReplaceHelper->replaceCsrfToken($expectedResponse);
        static::assertSame($expectedResponse, $response);
    }

    private function getContentWithCsrfPLaceholder(): string
    {
        $template = $this->twig->load('csrfTest.html.twig');

        return $template->render();
    }

    private function createCsrfReplaceHelper(bool $csrfEnabled = true, string $csrfMode = 'twig')
    {
        return new CsrfReplaceHelper(
            $this->getContainer()->get('security.csrf.token_manager'),
            $csrfEnabled,
            $csrfMode
        );
    }

    private function generateToken($intent)
    {
        $tokenManager = $this->getContainer()->get('security.csrf.token_manager');

        return $tokenManager->getToken($intent)->getValue();
    }
}
