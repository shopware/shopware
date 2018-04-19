<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal\FieldAccessorBuilder;

use Shopware\Api\Entity\Field\Field;
use Shopware\Context\Struct\ApplicationContext;

interface FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, ApplicationContext $context, string $accessor): ?string;
}
