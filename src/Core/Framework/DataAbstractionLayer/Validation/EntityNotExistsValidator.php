<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

#[Package('core')]
class EntityNotExistsValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly EntitySearcherInterface $entitySearcher
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EntityNotExists) {
            throw new UnexpectedTypeException($constraint, EntityNotExists::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        $definition = $this->definitionRegistry->getByEntityName($constraint->getEntity());

        $criteria = clone $constraint->getCriteria();
        $criteria->addFilter(new EqualsFilter($constraint->getPrimaryProperty(), $value));

        // Only one entity is enough to determine existence.
        // As the property can be set in the constraint, the search above does not necessarily return just one entity.
        $criteria->setLimit(1);

        $result = $this->entitySearcher->search($definition, $criteria, $constraint->getContext());

        if ($result->getTotal() <= 0) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ entity }}', $this->formatValue($constraint->getEntity()))
            ->setCode(EntityNotExists::ENTITY_EXISTS)
            ->addViolation();
    }
}
