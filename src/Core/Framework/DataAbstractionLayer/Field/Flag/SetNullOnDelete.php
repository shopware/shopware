<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * In case the referenced association data will be deleted, the related data will be set to null and an Written event will be thrown
 */
class SetNullOnDelete extends Flag
{
    public function parse(): \Generator
    {
        yield 'set_null_on_delete' => true;
    }
}
