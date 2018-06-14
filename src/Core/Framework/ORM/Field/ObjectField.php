<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ObjectField extends JsonField
{
    public function __construct(string $storageName, string $propertyName)
    {
        parent::__construct($storageName, $propertyName);
    }

    public function getInsertConstraints(): array
    {
        $constraints = parent::getInsertConstraints();
        $constraints[] = $this->getStructConstraint();

        return $constraints;
    }

    public function getUpdateConstraints(): array
    {
        $constraints = parent::getUpdateConstraints();
        $constraints[] = $this->getStructConstraint();

        return $constraints;
    }

    private function getStructConstraint(): Constraint
    {
        return new Callback([
           'callback' => function ($object, ExecutionContextInterface $context, $payload) {
               if ($object === null || $object instanceof Struct) {
                   return;
               }

               $context->buildViolation('The object must be of type "\Shopware\Core\Framework\Struct\Struct" to be persisted in a ObjectField.')
                    ->atPath($this->path)
                    ->addViolation()
                ;
           },
        ]);
    }
}
