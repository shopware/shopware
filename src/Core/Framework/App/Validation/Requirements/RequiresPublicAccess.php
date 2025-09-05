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
class RequiresPublicAccess extends AbstractRequirement implements ResetInterface
{
    private ?bool $isMet = null;

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
            return $this->isMet = false;
        }

        if (!$this->secureUrlValidator->isValidTarget($appUrl)) {
            return $this->isMet = false;
        }

        try {
            $response = $this->guzzle->get(rtrim($appUrl, '/') . '/api/_info/health-check', [
                RequestOptions::TIMEOUT => 1,
                RequestOptions::CONNECT_TIMEOUT => 1,
            ]);

            if ($response->getStatusCode() === Response::HTTP_OK) {
                return $this->isMet = true;
            }
        } catch (GuzzleException) {
            return $this->isMet = false;
        }

        return $this->isMet = false;
    }

    public function reset(): void
    {
        $this->isMet = null;
    }

    public static function name(): string
    {
        return 'requires-public-access';
    }

    public static function actionableResolution(): string
    {
        return 'The app requires public access to the Shopware instance. Ensure that the APP_URL environment variable is set to a publicly accessible URL.';
    }
}
