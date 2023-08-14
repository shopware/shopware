<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Symfony\Component\HttpFoundation\Response;

#[Package('buyers-experience')]
class LocaleException extends HttpException
{
    final public const LOCALE_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__LOCALE_DOES_NOT_EXISTS';
    final public const LANGUAGE_NOT_FOUND = 'SYSTEM__LANGUAGE_NOT_FOUND';

    public static function localeDoesNotExists(string $locale): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::LOCALE_DOES_NOT_EXISTS_EXCEPTION,
            'The locale {{ locale }} does not exists.',
            ['locale' => $locale]
        );
    }

    /**
     * @deprecated tag:v6.6.0 - reason:return-type-change - will return `self` in the future
     */
    public static function languageNotFound(?string $languageId): HttpException
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new LanguageNotFoundException($languageId);
        }

        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_NOT_FOUND,
            'The language "{{ languageId }}" was not found.',
            ['languageId' => $languageId]
        );
    }
}
