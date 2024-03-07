<?php declare(strict_types=1);

namespace Shopware\Core\Test\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

/**
 * @internal
 */
class InTestNamespace extends Field
{
    protected function getSerializerClass(): string
    {
        return self::class;
    }
}
