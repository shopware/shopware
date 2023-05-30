<?php declare(strict_types=1);

namespace Shopware\Core\System\Exception;

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[\Shopware\Core\Framework\Log\Package('core')]
class MissingTranslationLanguageException extends WriteConstraintViolationException
{
    final public const VIOLATION_MISSING_TRANSLATION_LANGUAGE = 'MISSING-TRANSLATION-LANGUAGE';

    public function __construct(
        string $path,
        int $translationIndex
    ) {
        $template = 'Translation requires a language id.';
        $constraintViolationList = new ConstraintViolationList([
            new ConstraintViolation(
                $template,
                $template,
                [],
                null,
                "/{$translationIndex}",
                null,
                null,
                self::VIOLATION_MISSING_TRANSLATION_LANGUAGE
            ),
        ]);
        parent::__construct($constraintViolationList, $path);
    }
}
