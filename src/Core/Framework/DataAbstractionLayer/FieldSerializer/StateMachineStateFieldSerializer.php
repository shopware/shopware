<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('core')]
class StateMachineStateFieldSerializer extends FkFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!($field instanceof StateMachineStateField)) {
            throw DataAbstractionLayerException::invalidSerializerField(StateMachineStateField::class, $field);
        }

        // Always allow any status when creating a new entity. A state transition from one state into another makes no
        // sense in that case.
        if (!$existence->exists()) {
            return parent::encode($field, $existence, $data, $parameters);
        }

        // Allow the change of the stateMachineState if the scope is one of the allowed ones.
        $scope = $parameters->getContext()->getContext()->getScope();
        if (\in_array($scope, $field->getAllowedWriteScopes(), true)) {
            return parent::encode($field, $existence, $data, $parameters);
        }

        // In every other case force the user to use a state-transition
        $messageTemplate = 'Changing the state-machine-state of this entity is not allowed for scope {{ scope }}. '
            . 'Either change the state-machine-state via a state-transition or use a different scope.';
        $messageParameters = [
            '{{ scope }}' => $scope,
        ];

        throw new WriteConstraintViolationException(new ConstraintViolationList([
            new ConstraintViolation(
                str_replace(array_keys($messageParameters), array_values($messageParameters), $messageTemplate),
                $messageTemplate,
                $messageParameters,
                null,
                '/' . $data->getKey(),
                $data->getValue()
            ),
        ]), $parameters->getPath());
    }
}
