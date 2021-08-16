<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Hmac\Guzzle;

use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\App\Hmac\RequestSigner;

class AuthMiddleware
{
    public const APP_REQUEST_TYPE = 'request_type';

    public const APP_SECRET = 'app_secret';

    public const VALIDATED_RESPONSE = 'validated_response';

    private string $shopwareVersion;

    public function __construct(string $shopwareVersion)
    {
        $this->shopwareVersion = $shopwareVersion;
    }

    public function __invoke(callable $handler): \Closure
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $request = $this->getDefaultHeaderRequest($request);

            if (!isset($options[self::APP_REQUEST_TYPE])) {
                return $handler($request, $options);
            }

            if (!\is_array($options[self::APP_REQUEST_TYPE])) {
                throw new InvalidArgumentException('request_type must be array');
            }

            $optionsRequestType = $options[self::APP_REQUEST_TYPE];

            if (!isset($optionsRequestType[self::APP_SECRET])) {
                throw new InvalidArgumentException('app_secret is required');
            }

            $secret = $optionsRequestType[self::APP_SECRET];

            $signature = new RequestSigner();

            $request = $signature->signRequest($request, $secret);

            $requiredAuthentic = empty($optionsRequestType[AuthMiddleware::VALIDATED_RESPONSE]) ? false : true;

            if (!$requiredAuthentic) {
                return $handler($request, $options);
            }

            $promise = function (ResponseInterface $response) use ($secret, $signature, $request) {
                if ($response->getStatusCode() !== 401) {
                    if (!$signature->isResponseAuthentic($response, $secret)) {
                        throw new ServerException(
                            'Could not verify the authenticity of the response',
                            $request,
                            $response
                        );
                    }
                }

                return $response;
            };

            return $handler($request, $options)->then($promise);
        };
    }

    public function getDefaultHeaderRequest(RequestInterface $request): RequestInterface
    {
        if ($request->hasHeader('sw-version')) {
            return clone $request;
        }

        return $request->withAddedHeader('sw-version', $this->shopwareVersion);
    }
}
