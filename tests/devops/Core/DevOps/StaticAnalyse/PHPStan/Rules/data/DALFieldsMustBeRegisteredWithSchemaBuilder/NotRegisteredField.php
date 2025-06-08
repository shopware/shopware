<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

/**
 * @internal
 */
class NotRegisteredField extends Field
{
    protected function getSerializerClass(): string
    {
        return self::class;
    }
}
