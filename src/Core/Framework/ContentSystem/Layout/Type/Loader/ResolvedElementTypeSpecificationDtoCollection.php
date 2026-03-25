<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Loader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ResolvedElementTypeSpecificationDtoCollection
{
    /**
     * @param list<ResolvedElementTypeSpecificationDto> $items
     */
    public function __construct(public array $items)
    {
    }

    public function validate(ValidatorInterface $validator): void
    {
        $allViolations = new ConstraintViolationList();

        foreach ($this->items as $resolved) {
            $violations = $validator->validate($resolved->dto);

            foreach ($violations as $violation) {
                $allViolations->add(new ConstraintViolation(
                    $violation->getMessage(),
                    $violation->getMessageTemplate(),
                    $violation->getParameters(),
                    $violation->getRoot(),
                    '[' . $resolved->name . '].' . $violation->getPropertyPath(),
                    $violation->getInvalidValue(),
                    $violation->getPlural(),
                    $violation->getCode(),
                    $violation->getConstraint(),
                    $violation->getCause(),
                ));
            }
        }

        if ($allViolations->count() > 0) {
            throw ContentSystemException::elementTypesInvalid($allViolations);
        }
    }

    /**
     * @return list<ContentSystemElementTypeSpecification>
     */
    public function toSpecifications(): array
    {
        return array_map(
            static fn (ResolvedElementTypeSpecificationDto $r) => $r->toSpecification(),
            $this->items,
        );
    }
}
