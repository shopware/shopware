<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\FieldAccessorBuilder;

use Shopware\Framework\Context;
use Shopware\Framework\ORM\Field\Field;

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
            $parsed = $builder->buildAccessor($root, $field, $context, $accessor);

            if ($parsed) {
                return $parsed;
            }
        }

        throw new \RuntimeException('No Field accessor builder found');
    }
}
