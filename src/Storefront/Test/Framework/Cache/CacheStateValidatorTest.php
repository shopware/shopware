<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Cache\CacheResponseSubscriber;
use Shopware\Storefront\Framework\Cache\CacheStateValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group cache
 */
class CacheStateValidatorTest extends TestCase
{
    /**
     * @dataProvider cases
     */
    public function testValidation(bool $isValid, Request $request, Response $response): void
    {
        $validator = new CacheStateValidator([]);
        static::assertSame($isValid, $validator->isValid($request, $response));
    }

    public function cases(): array
    {
        return [
            [true, new Request(), new Response()],
            [false, $this->createRequest('logged-in'), $this->createResponse('logged-in')],
            [true, $this->createRequest('logged-in'), $this->createResponse()],
            [true, $this->createRequest(), $this->createResponse('cart-filled')],
            [false, $this->createRequest('logged-in'), $this->createResponse('cart-filled', 'logged-in')],
            [false, $this->createRequest('cart-filled', 'logged-in'), $this->createResponse('cart-filled', 'logged-in')],
        ];
    }

    private function createRequest(string ...$states): Request
    {
        $request = new Request();
        $request->cookies->set(CacheResponseSubscriber::SYSTEM_STATE_COOKIE, implode(',', $states));

        return $request;
    }

    private function createResponse(string ...$states): Response
    {
        $response = new Response();
        $response->headers->set(CacheResponseSubscriber::INVALIDATION_STATES_HEADER, implode(',', $states));

        return $response;
    }
}
