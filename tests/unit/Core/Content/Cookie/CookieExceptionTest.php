<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CookieException::class)]
class CookieExceptionTest extends TestCase
{
    #[TestDox('invalid legacy cookie groups are JSON-encoded into the message')]
    public function testInvalidLegacyCookieGroupProvided(): void
    {
        $exception = CookieException::invalidLegacyCookieGroupProvided(['isRequired' => true]);

        static::assertSame('CONTENT__COOKIE_INVALID_LEGACY_COOKIE_GROUP_PROVIDED', $exception->getErrorCode());
        static::assertSame('Invalid legacy cookie group provided: {"isRequired":true}. The key "snippet_name" is required.', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    #[TestDox('invalid legacy cookie entries are JSON-encoded into the message')]
    public function testInvalidLegacyCookieEntryProvided(): void
    {
        $exception = CookieException::invalidLegacyCookieEntryProvided(['value' => '1']);

        static::assertSame('CONTENT__COOKIE_INVALID_LEGACY_COOKIE_ENTRY_PROVIDED', $exception->getErrorCode());
        static::assertSame('Invalid legacy cookie entry provided: {"value":"1"}. The key "cookie" is required.', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }
}
