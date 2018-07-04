<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal\FieldAccessorBuilder;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Dbal\Exception\FieldAccessorBuilderNotFoundException;
use Shopware\Core\Framework\ORM\Field\Field;

class FieldAccessorBuilderRegistry
{
    /**
     * @var FieldAccessorBuilderInterface[]
     */
    protected $builders;

    public function __construct(iterable $builders)
    {
        $this->builders = $builders;
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): string
    {
        foreach ($this->builders as $builder) {
            if ($parsed = $builder->buildAccessor($root, $field, $context, $accessor)) {
                return $parsed;
            }
        }

        throw new FieldAccessorBuilderNotFoundException($root . '.' . $field->getPropertyName());
    }
}
