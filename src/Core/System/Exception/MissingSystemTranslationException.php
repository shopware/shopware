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
    public const MISSING_SYSTEM_TRANSLATION_VIOLATION = 'MISSING-SYSTEM-TRANSLATION';

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
                self::MISSING_SYSTEM_TRANSLATION_VIOLATION
            ),
        ]);
        parent::__construct($constraintViolationList, $path, 0, null, '');
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
