<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Requirements;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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
    private ?bool $isMet = null;

    private string $failureReason = '';

    public function __construct(
        private readonly SecureUrlValidator $secureUrlValidator,
        private readonly Client $guzzle,
    ) {
    }

    public function satisfied(Manifest $manifest): bool
    {
        if ($this->isMet !== null) {
            return $this->isMet;
        }

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        if (!\is_string($appUrl)) {
            $this->failureReason = 'The APP_URL environment variable is not configured.';

            return $this->isMet = false;
        }

        if (!$this->secureUrlValidator->isValidTarget($appUrl)) {
            $this->failureReason = \sprintf(
                'APP_URL "%s" is not a valid public URL. It must use HTTPS, must not be an IP address, and must not use a reserved domain.',
                $appUrl
            );

            return $this->isMet = false;
        }

        $healthCheckUrl = rtrim($appUrl, '/') . '/api/_info/health-check';

        try {
            $response = $this->guzzle->get($healthCheckUrl, [
                RequestOptions::TIMEOUT => 1,
                RequestOptions::CONNECT_TIMEOUT => 1,
            ]);

            if ($response->getStatusCode() === Response::HTTP_OK) {
                return $this->isMet = true;
            }

            $this->failureReason = \sprintf(
                'Health check at "%s" returned HTTP %d. Ensure the Shopware instance is running and publicly reachable.',
                $healthCheckUrl,
                $response->getStatusCode()
            );
        } catch (GuzzleException) {
            $this->failureReason = \sprintf(
                'Could not reach "%s". Ensure the Shopware instance is publicly accessible at the configured APP_URL.',
                $healthCheckUrl
            );

            return $this->isMet = false;
        }

        return $this->isMet = false;
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

    public function actionableResolution(): string
    {
        if ($this->failureReason !== '') {
            return $this->failureReason;
        }

        return 'The app requires public access to the Shopware instance. Ensure that the APP_URL environment variable is set to a publicly accessible HTTPS URL.';
    }
}
