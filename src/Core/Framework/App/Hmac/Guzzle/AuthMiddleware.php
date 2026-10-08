<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Hmac\Guzzle;

use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @see Shopware\Tests\Integration\Core\Framework\App\Hmac\Guzzle\AuthMiddlewareTest
 */
#[Package('framework')]
class AuthMiddleware
{
    final public const APP_REQUEST_TYPE = 'request_type';

    final public const APP_SECRET = 'app_secret';

    final public const VALIDATED_RESPONSE = 'validated_response';

    final public const APP_REQUEST_CONTEXT = 'app_request_context';

    final public const SHOPWARE_CONTEXT_LANGUAGE = 'sw-context-language';

    final public const SHOPWARE_USER_LANGUAGE = 'sw-user-language';

    /**
     * Redirect policy for every app-system HTTP client. Without `strict`, Guzzle downgrades the
     * signed POST to a bodyless GET on a 301/302, so the forwarded request goes out unsigned.
     *
     * @see RequestSigner::signRequest()
     */
    final public const ALLOW_REDIRECTS = ['max' => 5, 'strict' => true];

    /**
     * @internal
     */
    public function __construct(
        private readonly string $shopwareVersion,
        private readonly AppLocaleProvider $localeProvider
    ) {
    }

    /**
     * @param callable(RequestInterface, array<mixed>): mixed $handler
     */
    public function __invoke(callable $handler): \Closure
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $request = $this->getDefaultHeaderRequest($request, $options);

            if (!isset($options[self::APP_REQUEST_TYPE])) {
                return $handler($request, $options);
            }

            if (!\is_array($options[self::APP_REQUEST_TYPE])) {
                /** @phpstan-ignore shopware.domainException (guzzle maintained exception) */
                throw new InvalidArgumentException('request_type must be array');
            }

            $optionsRequestType = $options[self::APP_REQUEST_TYPE];

            if (!isset($optionsRequestType[self::APP_SECRET])) {
                /** @phpstan-ignore shopware.domainException (guzzle maintained exception) */
                throw new InvalidArgumentException('app_secret is required');
            }

            $secret = $optionsRequestType[self::APP_SECRET];

            $signature = new RequestSigner();

            $request = $signature->signRequest($request, $secret);

            $requiredAuthentic = $optionsRequestType[AuthMiddleware::VALIDATED_RESPONSE] ?? false;

            if (!$requiredAuthentic) {
                return $handler($request, $options);
            }

            $successCallback = static function (ResponseInterface $response) use ($secret, $signature, $request) {
                if ($response->getStatusCode() !== 401) {
                    if (!$signature->isResponseAuthentic($response, $secret)) {
                        /** @phpstan-ignore shopware.domainException (guzzle maintained exception) */
                        throw new ServerException(
                            'Could not verify the authenticity of the response',
                            $request,
                            $response
                        );
                    }
                }

                return $response;
            };
            $promise = $handler($request, $options);
            \assert($promise instanceof PromiseInterface);

            return $promise->then($successCallback);
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getDefaultHeaderRequest(RequestInterface $request, array $options): RequestInterface
    {
        if (isset($options[self::APP_REQUEST_CONTEXT])) {
            $context = $options[self::APP_REQUEST_CONTEXT];
            if (!$context instanceof Context) {
                /** @phpstan-ignore shopware.domainException (guzzle maintained exception) */
                throw new InvalidArgumentException('app_request_context must be instance of Context');
            }
            $request = $this->getLanguageHeaderRequest($request, $context);
        }

        if ($request->hasHeader('sw-version')) {
            return clone $request;
        }

        return $request->withAddedHeader('sw-version', $this->shopwareVersion);
    }

    private function getLanguageHeaderRequest(RequestInterface $request, Context $context): RequestInterface
    {
        $request = $request->withAddedHeader(self::SHOPWARE_CONTEXT_LANGUAGE, $context->getLanguageId());

        return $request->withAddedHeader(self::SHOPWARE_USER_LANGUAGE, $this->localeProvider->getLocaleFromContext($context));
    }
}
