<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AppUrlVerifier
{
    private Client $guzzle;

    private string $appEnv;

    public function __construct(Client $guzzle, string $appEnv)
    {
        $this->guzzle = $guzzle;
        $this->appEnv = $appEnv;
    }

    public function isAppUrlReachable(Request $request): bool
    {
        if ($this->appEnv !== 'prod') {
            // dev and test system are often not reachable and this is totally fine
            // problems occur if a prod system can't be reached
            return true;
        }

        /** @var string $appUrl */
        $appUrl = EnvironmentHelper::getVariable('APP_URL');

        if (str_starts_with($request->getUri(), $appUrl)) {
            // if the request was made to the domain as the APP_URL we know that it can be reached
            return true;
        }

        try {
            $response = $this->guzzle->get(rtrim($appUrl, '/') . '/api/_info/version', [
                'headers' => [
                    'Authorization' => $request->headers->get('Authorization'),
                ],
            ]);

            if ($response->getStatusCode() === Response::HTTP_OK) {
                return true;
            }
        } catch (GuzzleException $e) {
            return false;
        }

        return false;
    }
}
