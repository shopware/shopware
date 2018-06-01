<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\FieldAccessorBuilder;

use Shopware\Framework\Context;
use Shopware\Framework\ORM\Field\Field;

interface FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string;
}
