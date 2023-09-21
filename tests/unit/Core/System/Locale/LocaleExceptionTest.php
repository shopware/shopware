<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Locale;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\System\Locale\LocaleException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\Locale\LocaleException
 */
#[Package('buyers-experience')]
class LocaleExceptionTest extends TestCase
{
    public function testLocaleDoesNotExist(): void
    {
        $e = LocaleException::localeDoesNotExists('myCustomLocale');

        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame(LocaleException::LOCALE_DOES_NOT_EXISTS_EXCEPTION, $e->getErrorCode());
    }

    public function testLanguageNotFound(): void
    {
        $e = LocaleException::languageNotFound('foo');

        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $e->getStatusCode());
        static::assertSame(LocaleException::LANGUAGE_NOT_FOUND, $e->getErrorCode());
    }

    /**
     * @DisabledFeatures("v6.6.0.0")
     *
     * @deprecated tag:v6.6.0.0
     */
    public function testLanguageNotFoundLegacy(): void
    {
        $e = LocaleException::languageNotFound('foo');

        static::assertInstanceOf(LanguageNotFoundException::class, $e);
    }
}
