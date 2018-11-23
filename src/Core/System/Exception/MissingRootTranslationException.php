<?php declare(strict_types=1);

namespace Shopware\Core\System\Exception;

namespace Shopware\Core\System\Exception;

use Shopware\Core\Framework\Validation\ConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class MissingRootTranslationException extends ConstraintViolationException
{
    public const MISSING_ROOT_TRANSLATION_VIOLATION = 'MISSING-ROOT-TRANSLATION';

    public function __construct(string $rootId, string $childId, string $path = '')
    {
        $template = 'Translation for root language {{ rootId }} required for child language {{ childId }}.';
        $parameters = [
            '{{ rootId }}' => $rootId,
            '{{ childId }}' => $childId,
        ];
        $constraintViolationList = new ConstraintViolationList([
            new ConstraintViolation(
                str_replace(array_keys($parameters), array_values($parameters), $template),
                $template,
                $parameters,
                null,
                $path,
                $childId,
                null,
                self::MISSING_ROOT_TRANSLATION_VIOLATION
            ),
        ]);
        parent::__construct($constraintViolationList, $path, 0, null, '');
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
