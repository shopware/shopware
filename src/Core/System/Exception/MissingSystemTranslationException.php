<?php declare(strict_types=1);

namespace Shopware\Core\System\Exception;

namespace Shopware\Core\System\Exception;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Validation\ConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class MissingSystemTranslationException extends ConstraintViolationException
{
    public const VIOLATION_MISSING_SYSTEM_TRANSLATION = 'MISSING-SYSTEM-TRANSLATION';

    public function __construct(string $path = '')
    {
        $template = 'Translation required for system language {{ systemLanguage }}';
        $parameters = ['{{ systemLanguage }}' => Defaults::LANGUAGE_SYSTEM];
        $constraintViolationList = new ConstraintViolationList([
            new ConstraintViolation(
                str_replace(array_keys($parameters), array_values($parameters), $template),
                $template,
                $parameters,
                null,
                $path,
                Defaults::LANGUAGE_SYSTEM,
                null,
                self::VIOLATION_MISSING_SYSTEM_TRANSLATION
            ),
        ]);
        parent::__construct($constraintViolationList, $path);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
