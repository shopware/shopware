<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

interface FieldSerializerInterface
{
    /**
     * Encodes the provided DAL value to a persitable storage value
     *
     * @param Field             $field
     * @param EntityExistence   $existence
     * @param KeyValuePair      $data
     * @param WriteParameterBag $parameters
     *
     * @return \Generator
     */
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator;

    /**
     * Decodes the storage value to the DAL value
     *
     * @param Field $field
     * @param mixed $value
     *
     * @return mixed
     */
    public function decode(Field $field, $value);

    /**
     * Defines which field can be encoded and decoded by this handler
     *
     * @return string
     */
    public function getFieldClass(): string;
}
