<?php declare(strict_types=1);

namespace Shopware\Core\Test\Field;

use Shopware\SomewhereElse\Framework\DataAbstractionLayer\Field\Field;

/**
 * @internal
 */
class NotInCoreNamespace extends Field
{
    protected function getSerializerClass(): string
    {
        return self::class;
    }
}
