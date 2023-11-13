<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Cache\CacheResponseSubscriber;
use Shopware\Storefront\Framework\Cache\CacheStateValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
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

    public static function cases(): array
    {
        return [
            [true, new Request(), new Response()],
            [false, self::createRequest('logged-in'), self::createResponse('logged-in')],
            [true, self::createRequest('logged-in'), self::createResponse()],
            [true, self::createRequest(), self::createResponse('cart-filled')],
            [false, self::createRequest('logged-in'), self::createResponse('cart-filled', 'logged-in')],
            [false, self::createRequest('cart-filled', 'logged-in'), self::createResponse('cart-filled', 'logged-in')],
        ];
    }

    private static function createRequest(string ...$states): Request
    {
        $request = new Request();
        $request->cookies->set(CacheResponseSubscriber::SYSTEM_STATE_COOKIE, implode(',', $states));

        return $request;
    }

    private static function createResponse(string ...$states): Response
    {
        $response = new Response();
        $response->headers->set(CacheResponseSubscriber::INVALIDATION_STATES_HEADER, implode(',', $states));

        return $response;
    }
}
