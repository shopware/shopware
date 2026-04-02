<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Requirements;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('framework')]
class PublicAccess extends AbstractRequirement implements ResetInterface
{
    /**
     * Caching is safe at the DI/service level: the check is app-independent (APP_URL, HTTPS,
     * reachability), so if it fails for one app it will fail for every app in the same process.
     */
    private ?bool $isMet = null;

    private string $failureReason = '';

    public function __construct(
        private readonly SecureUrlValidator $secureUrlValidator,
        private readonly Client $guzzle,
    ) {
    }

    public function validate(Manifest $manifest): ?UnmetRequirement
    {
        if ($this->isMet !== null) {
            if ($this->isMet) {
                return null;
            }

            return new UnmetRequirement($manifest->getMetadata()->getName(), self::name(), $this->failureReason);
        }

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        if (!\is_string($appUrl)) {
            return $this->fail($manifest, 'The APP_URL environment variable is not configured.');
        }

        if (!$this->secureUrlValidator->isValidTarget($appUrl)) {
            return $this->fail($manifest, \sprintf(
                'APP_URL "%s" is not a valid public URL. It must use HTTPS, must not be an IP address, must not use a reserved domain, and its host must resolve via DNS to a public IP address.',
                $appUrl
            ));
        }

        $result = $this->checkHealthEndpoint($manifest, rtrim($appUrl, '/') . '/api/_info/health-check');

        return $result ?? $this->succeed();
    }

    public function reset(): void
    {
        $this->isMet = null;
        $this->failureReason = '';
    }

    public static function name(): string
    {
        return 'public-access';
    }

    private function succeed(): null
    {
        $this->isMet = true;
        $this->failureReason = '';

        return null;
    }

    private function checkHealthEndpoint(Manifest $manifest, string $healthCheckUrl): ?UnmetRequirement
    {
        try {
            $response = $this->guzzle->get($healthCheckUrl, [
                RequestOptions::TIMEOUT => 1,
                RequestOptions::CONNECT_TIMEOUT => 1,
                RequestOptions::ALLOW_REDIRECTS => false,
            ]);

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                return $this->fail($manifest, \sprintf(
                    'Health check at "%s" returned HTTP %d. Ensure the Shopware instance is running and publicly reachable.',
                    $healthCheckUrl,
                    $response->getStatusCode()
                ));
            }
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response !== null) {
                return $this->fail($manifest, \sprintf(
                    'Health check at "%s" returned HTTP %d. Ensure the Shopware instance is running and publicly reachable.',
                    $healthCheckUrl,
                    $response->getStatusCode()
                ));
            }

            return $this->fail($manifest, \sprintf(
                'Could not reach "%s". Ensure the Shopware instance is publicly accessible at the configured APP_URL.',
                $healthCheckUrl
            ));
        } catch (GuzzleException) {
            return $this->fail($manifest, \sprintf(
                'Could not reach "%s". Ensure the Shopware instance is publicly accessible at the configured APP_URL.',
                $healthCheckUrl
            ));
        }

        return null;
    }

    private function fail(Manifest $manifest, string $reason): UnmetRequirement
    {
        $this->isMet = false;
        $this->failureReason = $reason;

        return new UnmetRequirement($manifest->getMetadata()->getName(), self::name(), $reason);
    }
}
