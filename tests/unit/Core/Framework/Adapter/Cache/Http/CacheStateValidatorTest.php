<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheStateValidator;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CacheStateValidator::class)]
#[Group('cache')]
class CacheStateValidatorTest extends TestCase
{
    #[DataProvider('cases')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidation(bool $isValid, Request $request, Response $response): void
    {
        $validator = new CacheStateValidator([]);
        static::assertSame($isValid, $validator->isValid($request, $response));
    }

    /**
     * @return array<array{bool, Request, Response}>
     */
    public static function cases(): array
    {
        return [
            'states and invalidation states are empty' => [true, new Request(), new Response()],
            'states match' => [false, self::createRequest('logged-in'), self::createResponse(['logged-in'])],
            'invalidation states are empty' => [true, self::createRequest('logged-in'), self::createResponse()],
            'states are empty' => [true, self::createRequest(), self::createResponse(['cart-filled'])],
            'one of multiple states match' => [false, self::createRequest('logged-in'), self::createResponse(['cart-filled', 'logged-in'])],
            'multiple states match' => [false, self::createRequest('cart-filled', 'logged-in'), self::createResponse(['cart-filled', 'logged-in'])],
            'Response cookie overwrites/adds request value' => [false, self::createRequest('logged-in'), self::createResponse(['cart-filled'], ['cart-filled'])],
            'Response cookie overwrites/removes request value' => [true, self::createRequest('cart-filled'), self::createResponse(['cart-filled'], [''])],
        ];
    }

    private static function createRequest(string ...$states): Request
    {
        $request = new Request();
        $request->cookies->set(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, implode(',', $states));

        return $request;
    }

    /**
     * @param list<string> $invalidationStates
     * @param list<string> $cookieStates
     */
    private static function createResponse(array $invalidationStates = [], array $cookieStates = []): Response
    {
        $response = new Response();
        $response->headers->set(HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER, implode(',', $invalidationStates));

        foreach ($cookieStates as $state) {
            $response->headers->setCookie(new Cookie(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, $state));
        }

        return $response;
    }
}
