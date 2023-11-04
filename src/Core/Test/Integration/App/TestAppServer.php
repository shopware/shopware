<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\App;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @internal
 */
class TestAppServer
{
    final public const TEST_SETUP_SECRET = 's3cr3t';
    final public const CONFIRMATION_URL = 'https://my-app.com/confirm';
    final public const APP_SECRET = 'dont_tell';

    private ?RequestInterface $registrationRequest = null;

    private ?RequestInterface $confirmationRequest = null;

    public function __construct(private readonly MockHandler $inner)
    {
    }

    /**
     * @param array<mixed> $options
     */
    public function __invoke(RequestInterface $request, array $options): Promise
    {
        if ($this->inner->count() > 0) {
            return \call_user_func($this->inner, $request, $options);
        }

        if ($this->isRegistration($request)) {
            $this->registrationRequest = $request;
            $promise = new Promise();
            $promise->resolve(new Response(200, [], $this->buildAppResponse($request)));

            return $promise;
        }

        if ($this->isRegistrationConfirmation($request)) {
            $this->confirmationRequest = $request;
            $promise = new Promise();
            $promise->resolve(new Response(200));

            return $promise;
        }

        return \call_user_func($this->inner, $request, $options);
    }

    public function didRegister(): bool
    {
        return $this->registrationRequest !== null && $this->confirmationRequest !== null;
    }

    public function reset(): void
    {
        $this->registrationRequest = null;
        $this->confirmationRequest = null;
    }

    private function buildAppResponse(RequestInterface $request): string
    {
        $shopUrl = $this->getQueryParameter($request, 'shop-url');
        $appname = $this->getAppname($request);
        $shopId = $this->getQueryParameter($request, 'shop-id');

        $proof = \hash_hmac('sha256', $shopId . $shopUrl . $appname, self::TEST_SETUP_SECRET);

        return (string) \json_encode(['proof' => $proof, 'secret' => self::APP_SECRET, 'confirmation_url' => self::CONFIRMATION_URL], \JSON_THROW_ON_ERROR);
    }

    private function isRegistration(RequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        $pathElems = \explode('/', $path);

        return ($pathElems[2] ?? '') === 'registration';
    }

    private function isRegistrationConfirmation(RequestInterface $request): bool
    {
        return ((string) $request->getUri()) === self::CONFIRMATION_URL;
    }

    private function getQueryParameter(RequestInterface $request, string $param): string
    {
        $query = [];
        \parse_str($request->getUri()->getQuery(), $query);

        TestCase::assertIsString($query[$param]);

        return $query[$param];
    }

    private function getAppname(RequestInterface $request): string
    {
        $path = $request->getUri()->getPath();
        $pathElems = \explode('/', $path);

        return $pathElems[1] ?? '';
    }
}
