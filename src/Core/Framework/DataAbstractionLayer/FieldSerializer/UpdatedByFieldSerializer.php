<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class UpdatedByFieldSerializer extends FkFieldSerializer
{
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!($field instanceof UpdatedByField)) {
            throw DataAbstractionLayerException::invalidSerializerField(UpdatedByField::class, $field);
        }

        if (!$existence->exists()) {
            return;
        }

        $context = $parameters->getContext()->getContext();
        $scope = $context->getScope();

        if (!\in_array($scope, $field->getAllowedWriteScopes(), true)) {
            return;
        }

        if (!$context->getSource() instanceof AdminApiSource) {
            return;
        }

        $userId = $context->getSource()->getUserId();

        if (!$userId) {
            return;
        }

        $data->setValue($userId);

        yield from parent::encode($field, $existence, $data, $parameters);
    }
}
