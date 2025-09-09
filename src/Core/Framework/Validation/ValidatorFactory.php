<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

#[Package('framework')]
class ValidatorFactory
{
    /**
     * @deprecated tag:v6.8.0 reason:new-optional-parameter - Parameter $allowExtraFields will be added
     *
     * @template TClassToCreate of object
     *
     * @param array<string, mixed> $data
     * @param class-string<TClassToCreate> $class
     *
     * @return TClassToCreate
     */
    public static function create(array $data, string $class /* , bool $allowExtraFields = false */): object
    {
        $allowExtraFields = \func_num_args() > 2 && \func_get_arg(2);

        $validator = Validation::createValidator();
        $constraints = self::getConstraints($class);
        $violations = $validator->validate($data, new Collection($constraints, allowExtraFields: $allowExtraFields));

        if ($violations->count() === 0) {
            return new $class($data);
        }

        $messages = array_map(
            fn (ConstraintViolationInterface $violation) => $violation->getPropertyPath() . ': ' . $violation->getMessage(),
            iterator_to_array($violations)
        );

        throw FrameworkException::validationFailed('Invalid or missing data (' . implode(', ', $messages) . ')');
    }

    /**
     * @return array<string, mixed>
     */
    private static function getConstraints(string $class): array
    {
        if (!class_exists($class)) {
            throw FrameworkException::classNotFound($class);
        }

        $reflectionClass = new \ReflectionClass($class);
        $constraints = [];

        foreach ($reflectionClass->getProperties() as $property) {
            $attributes = $property->getAttributes();
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $constraints[$property->getName()][] = $instance;
            }
        }

        return $constraints;
    }
}
