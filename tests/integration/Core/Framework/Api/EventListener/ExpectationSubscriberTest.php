<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\EventListener;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
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

    public function testPublicApiRouteRejectsTheHeaderWithoutDisclosingVersions(): void
    {
        $content = $this->requestHealthCheck('shopware/core:~0.1,symfony/http-kernel:~0.1', Response::HTTP_EXPECTATION_FAILED);

        static::assertStringContainsString(ApiException::API_EXPECTATION_NOT_SUPPORTED, $content);
        static::assertStringNotContainsString('Installed is', $content);
    }

    public function testPublicApiRouteRejectionDoesNotRevealWhetherAPackageIsInstalled(): void
    {
        $installed = $this->requestHealthCheck('shopware/core:~0.1', Response::HTTP_EXPECTATION_FAILED);
        $notInstalled = $this->requestHealthCheck('swag/not-installed:*', Response::HTTP_EXPECTATION_FAILED);

        static::assertStringNotContainsString('is not available', $notInstalled);
        static::assertSame(
            $this->errorsWithoutTrace($installed),
            $this->errorsWithoutTrace($notInstalled),
            'The rejection must not differ between an installed and a missing package.'
        );
    }

    /**
     * Docker `HEALTHCHECK` polls this route without the header.
     */
    public function testPublicApiRouteSucceedsWhenNoHeaderIsSent(): void
    {
        $this->requestHealthCheck(null, Response::HTTP_OK);
    }

    public function testAuthenticatedApiRouteRequiresAuthenticationBeforeExpectations(): void
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

    /**
     * The `trace` key only exists in dev and carries the caller's own line numbers.
     *
     * @return array<mixed>
     */
    private function errorsWithoutTrace(string $content): array
    {
        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertIsArray($decoded['errors'] ?? null);

        return array_map(static function (mixed $error): mixed {
            if (\is_array($error)) {
                unset($error['trace']);
            }

            return $error;
        }, $decoded['errors']);
    }

    private function requestHealthCheck(?string $expectPackages, int $expectedStatus): string
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            Request::METHOD_GET,
            '/api/_info/health-check',
            server: $expectPackages === null ? [] : ['HTTP_SW_EXPECT_PACKAGES' => $expectPackages]
        );

        $response = $browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame($expectedStatus, $response->getStatusCode(), $content);

        return $content;
    }
}
