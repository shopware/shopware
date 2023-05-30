<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('core')]
class AppUrlVerifier
{
    public function __construct(
        private readonly Client $guzzle,
        private readonly Connection $connection,
        private readonly string $appEnv,
        private readonly bool $appUrlCheckDisabled
    ) {
    }

    public function isAppUrlReachable(Request $request): bool
    {
        if ($this->appEnv !== 'prod' || $this->appUrlCheckDisabled) {
            // dev and test system are often not reachable and this is totally fine
            // problems occur if a prod system can't be reached
            // the check can be disabled manually e.g. for cloud
            return true;
        }

        /** @var string $appUrl */
        $appUrl = EnvironmentHelper::getVariable('APP_URL');

        if (str_starts_with($request->getUri(), $appUrl)) {
            // if the request was made to the same domain as the APP_URL we know that it can be reached
            return true;
        }

        try {
            $response = $this->guzzle->get(rtrim($appUrl, '/') . '/api/_info/version', [
                'headers' => [
                    'Authorization' => $request->headers->get('Authorization'),
                ],
                RequestOptions::TIMEOUT => 1,
                RequestOptions::CONNECT_TIMEOUT => 1,
            ]);

            if ($response->getStatusCode() === Response::HTTP_OK) {
                return true;
            }
        } catch (GuzzleException) {
            return false;
        }

        return false;
    }

    public function hasAppsThatNeedAppUrl(): bool
    {
        $foundApp = $this->connection->fetchOne('SELECT 1 FROM app WHERE app_secret IS NOT NULL');

        return $foundApp === '1';
    }
}
