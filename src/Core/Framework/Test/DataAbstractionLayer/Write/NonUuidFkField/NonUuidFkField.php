<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\NonUuidFkField;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;

/**
 * @internal test class
 */
class NonUuidFkField extends FkField
{
    protected function getSerializerClass(): string
    {
        return NonUuidFkFieldSerializer::class;
    }
}
