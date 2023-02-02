<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
interface FieldSerializerInterface
{
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array;

    /**
     * Encodes the provided DAL value to a persitable storage value
     */
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator;

    /**
     * Decodes the storage value to the DAL value
     */
    public function decode(Field $field, mixed $value): mixed;
}
