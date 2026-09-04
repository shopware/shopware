<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Maps diagnostics {@see Violation}s onto Symfony constraint violations so the DAL gates can accumulate them
 * onto the write. Each violation rides in the code (the ViolationCode value), the message, and the property
 * path (/{elementId}/{key}), so a batch write reports every violation rather than short-circuiting on the first.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ViolationConstraintMapper
{
    /**
     * @param list<Violation> $violations
     */
    public function toConstraintViolationList(array $violations): ConstraintViolationList
    {
        $list = new ConstraintViolationList();

        foreach ($violations as $violation) {
            $list->add(new ConstraintViolation(
                $violation->message,
                $violation->message,
                [],
                null,
                $this->propertyPath($violation),
                null,
                null,
                $violation->code->value,
            ));
        }

        return $list;
    }

    private function propertyPath(Violation $violation): string
    {
        if ($violation->key === null) {
            return '/' . $violation->elementId;
        }

        return '/' . $violation->elementId . '/' . $violation->key;
    }
}
