<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class EntityNotExistsValidator extends ConstraintValidator
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
        if (!$constraint instanceof EntityNotExists) {
            throw new UnexpectedTypeException($constraint, EntityNotExists::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        $definition = $this->definitionRegistry->getByEntityName($constraint->getEntity());

        $criteria = $constraint->getCriteria();

        $result = $this->entitySearcher->search($definition, $criteria, $constraint->getContext());

        if (!$result->getTotal()) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ entity }}', $this->formatValue($constraint->getEntity()))
            ->setCode(EntityNotExists::ENTITY_EXISTS)
            ->addViolation();
    }
}
