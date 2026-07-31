<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\EventListener;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Exception\ExpectationFailedException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ExpectationSubscriberTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * The `sw-expect-packages` failure messages contain installed package versions. On routes that do not
     * require authentication the header must be ignored, otherwise anyone can probe the dependency tree.
     */
    public function testPublicApiRouteDoesNotDiscloseInstalledVersions(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            Request::METHOD_GET,
            '/api/_info/health-check',
            server: ['HTTP_SW_EXPECT_PACKAGES' => 'shopware/core:~0.1,symfony/http-kernel:~0.1']
        );

        $response = $browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertStringNotContainsString((new ExpectationFailedException([]))->getErrorCode(), $content);
        static::assertStringNotContainsString('Installed is', $content);
    }

    public function testPublicApiRouteDoesNotDiscloseWhetherAPackageIsInstalled(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            Request::METHOD_GET,
            '/api/_info/health-check',
            server: ['HTTP_SW_EXPECT_PACKAGES' => 'swag/not-installed:*']
        );

        $response = $browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertStringNotContainsString('is not available', $content);
    }

    public function testAuthenticatedApiRouteStillRequiresAuthenticationBeforeExpectations(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            Request::METHOD_GET,
            '/api/tax',
            server: ['HTTP_SW_EXPECT_PACKAGES' => 'shopware/core:~0.1']
        );

        $response = $browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), $content);
        static::assertStringNotContainsString('Installed is', $content);
    }
}
