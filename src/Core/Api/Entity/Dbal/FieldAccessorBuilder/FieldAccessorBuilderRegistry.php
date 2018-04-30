<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal\FieldAccessorBuilder;

use Shopware\Api\Entity\Field\Field;
use Shopware\Context\Struct\ApplicationContext;

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

    public function buildAccessor(string $root, Field $field, ApplicationContext $context, string $accessor): string
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
