<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client;

use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Response;

class GoogleShoppingClient extends \Google_Client
{
    public function __construct(
        ?string $clientId,
        ?string $clientSecret,
        ?string $redirectUri
    ) {
        parent::__construct([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'access_type' => 'offline',
            'redirect_uri' => $redirectUri,
            'include_granted_scopes' => true,
        ]);
    }

    /**
     * Send array of RequestInterface request concurrently
     */
    public function asyncRequests(array $asyncRequests = [], ?bool $skipErrors = true, ?int $concurrency = 5): array
    {
        $client = $this->authorize();

        $responses = [];
        $errors = [];

        $exceptionHandler = function (\Exception $exception) use ($skipErrors, &$errors): void {
            if (!$skipErrors) {
                throw $exception;
            }

            $errors[] = $exception;
        };

        $pool = new Pool($client, $asyncRequests, [
            'concurrency' => $concurrency,
            'fulfilled' => function (Response $response) use (&$responses, $exceptionHandler): void {
                try {
                    $responses[] = json_decode($response->getBody()->getContents(), true);
                } catch (\Exception $exception) {
                    $exceptionHandler($exception);
                }
            },
            'rejected' => $exceptionHandler,
        ]);

        $promise = $pool->promise();

        $promise->wait();

        return compact('responses', 'errors');
    }

    /**
     * Defer requests wrapper
     */
    public function deferExecute(callable $callback)
    {
        $this->setDefer(true);

        $response = $callback();

        $this->setDefer(false);

        return $response;
    }
}
