<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
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
     * The duplicate-id list both write boundaries raise: {@see StoredTree::duplicateElementIds()} reports ids,
     * the DAL and the draft API need constraint violations, and this is the one place that bridges the two so
     * a change to how the defect is reported does not have to be made per boundary.
     *
     * @param list<string> $ids
     */
    public function fromDuplicateElementIds(array $ids): ConstraintViolationList
    {
        return $this->toConstraintViolationList(array_map(Violation::duplicateElementId(...), $ids));
    }

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
