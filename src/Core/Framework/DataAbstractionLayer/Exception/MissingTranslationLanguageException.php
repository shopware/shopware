<?php declare(strict_types=1);

namespace Shopware\Core\System\Exception;

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class MissingTranslationLanguageException extends WriteConstraintViolationException
{
    public const VIOLATION_MISSING_TRANSLATION_LANGUAGE = 'MISSING-TRANSLATION-LANGUAGE';

    public function __construct(string $path = '')
    {
        $template = 'Translation requires a language id.';
        $constraintViolationList = new ConstraintViolationList([
            new ConstraintViolation(
                $template,
                $template,
                [],
                null,
                $path,
                null,
                null,
                self::VIOLATION_MISSING_TRANSLATION_LANGUAGE
            ),
        ]);
        parent::__construct($constraintViolationList, $path);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
