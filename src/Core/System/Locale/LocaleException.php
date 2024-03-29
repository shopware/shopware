<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
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

    public static function languageNotFound(?string $languageId): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'language', 'field' => 'id', 'value' => $languageId]
        );
    }
}
