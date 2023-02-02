<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class LocaleException extends HttpException
{
    public const LOCALE_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__LOCALE_DOES_NOT_EXISTS';

    public static function localeDoesNotExists(string $locale): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::LOCALE_DOES_NOT_EXISTS_EXCEPTION,
            'The locale {{ locale }} does not exists.',
            ['locale' => $locale]
        );
    }
}
