<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Storefront\Framework\Twig\TwigAppVariable;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class TwigAppVariableTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRequestCycleDoesntTouchActualRequest(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());

        $browser->request('GET', $_SERVER['APP_URL']);
        static::assertTrue($browser->getRequest()->server->has('SERVER_PROTOCOL'));
    }

    public function testRequestGetsCloned(): void
    {
        $orgRequest = new Request();

        $appVariable = $this->createMock(AppVariable::class);
        $appVariable->method('getRequest')->willReturn($orgRequest);

        $app = new TwigAppVariable($appVariable);

        static::assertNotSame($orgRequest, $app->getRequest());
    }

    public function testClonedRequestLosesServerVars(): void
    {
        $orgRequest = new Request();
        $orgRequest->server->set('good', '1');
        $orgRequest->server->set('bad', '1');

        $appVariable = $this->createMock(AppVariable::class);
        $appVariable->method('getRequest')->willReturn($orgRequest);

        $app = new TwigAppVariable($appVariable, ['good']);
        $appRequest = $app->getRequest();

        static::assertNotNull($appRequest);
        static::assertNotSame($orgRequest, $appRequest);
        static::assertTrue($orgRequest->server->has('bad'));
        static::assertTrue($orgRequest->server->has('good'));
        static::assertTrue($appRequest->server->has('good'));
        static::assertFalse($appRequest->server->has('bad'));
    }

    public function testHttpsRequest(): void
    {
        $orgRequest = new Request();
        $orgRequest->server->set('HTTPS', '1');
        $orgRequest->server->set('SERVER_NAME', 'localhost');
        $orgRequest->server->set('SERVER_PORT', '443');

        static::assertTrue($orgRequest->isSecure());

        $appVariable = $this->createMock(AppVariable::class);
        $appVariable->method('getRequest')->willReturn($orgRequest);

        $app = new TwigAppVariable($appVariable, $this->getContainer()->getParameter('shopware.twig.app_variable.allowed_server_params'));
        $appRequest = $app->getRequest();

        static::assertNotNull($appRequest);
        static::assertTrue($appRequest->isSecure());
        static::assertSame('https://localhost', $appRequest->getSchemeAndHttpHost());
    }
}
