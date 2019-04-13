<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class EntityExistsValidator extends ConstraintValidator
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var EntitySearcherInterface
     */
    private $entitySearcher;

    public function __construct(DefinitionInstanceRegistry $definitionRegistry, EntitySearcherInterface $entitySearcher)
    {
        $this->definitionRegistry = $definitionRegistry;
        $this->entitySearcher = $entitySearcher;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof EntityExists) {
            throw new UnexpectedTypeException($constraint, EntityExists::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        $definition = $this->definitionRegistry->getByEntityName($constraint->getEntity());

        $criteria = $constraint->getCriteria();
        $criteria->addFilter(new EqualsFilter('id', $value));

        $result = $this->entitySearcher->search($definition, $criteria, $constraint->getContext());

        if ($result->getTotal()) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ id }}', $this->formatValue($value))
            ->setParameter('{{ entity }}', $this->formatValue($constraint->getEntity()))
            ->setCode(EntityExists::ENTITY_DOES_NOT_EXISTS)
            ->addViolation();
    }
}
