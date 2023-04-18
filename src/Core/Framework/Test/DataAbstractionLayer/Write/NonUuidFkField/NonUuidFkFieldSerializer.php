<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\NonUuidFkField;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

/**
 * @internal test class
 */
class NonUuidFkFieldSerializer implements FieldSerializerInterface
{
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        /** @var StorageAware $field */
        yield $field->getStorageName() => $data->getValue();
    }

    public function decode(Field $field, mixed $value): mixed
    {
        return $value;
    }

    /**
     * @param array<string, array<string, mixed>> $data
     *
     * @return array<string, array<string, mixed>>
     */
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        return $data;
    }
}
