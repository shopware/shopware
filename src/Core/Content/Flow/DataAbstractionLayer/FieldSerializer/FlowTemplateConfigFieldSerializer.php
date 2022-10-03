<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Content\Flow\DataAbstractionLayer\Field\FlowTemplateConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
class FlowTemplateConfigFieldSerializer extends JsonFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof FlowTemplateConfigField) {
            throw new InvalidSerializerFieldException(FlowTemplateConfigField::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();
        if (!\array_key_exists('description', $value)) {
            $value['description'] = null;
        }

        if (\array_key_exists('sequences', $value)) {
            foreach ($value['sequences'] as $index => $sequence) {
                if (!\array_key_exists('parentId', $sequence)) {
                    $value['sequences'][$index]['parentId'] = null;
                }

                if (!\array_key_exists('ruleId', $sequence)) {
                    $value['sequences'][$index]['ruleId'] = null;
                }

                if (!\array_key_exists('position', $sequence)) {
                    $value['sequences'][$index]['position'] = 1;
                }

                if (!\array_key_exists('displayGroup', $sequence)) {
                    $value['sequences'][$index]['displayGroup'] = 1;
                }

                if (!\array_key_exists('trueCase', $sequence)) {
                    $value['sequences'][$index]['trueCase'] = 0;
                }
            }
        }

        if ($value !== null) {
            $value = JsonFieldSerializer::encodeJson($value);
        }

        yield $field->getStorageName() => $value;
    }

    protected function getConstraints(Field $field): array
    {
        return [
            new Collection([
                'allowExtraFields' => true,
                'allowMissingFields' => false,
                'fields' => [
                    'eventName' => [new NotBlank(), new Type('string')],
                    'description' => [new Type('string')],
                    'sequences' => [
                        [
                            new Optional(
                                new Collection([
                                    'allowExtraFields' => true,
                                    'allowMissingFields' => false,
                                    'fields' => [
                                        'id' => [new NotBlank(), new Uuid()],
                                        'actionName' => [new NotBlank(), new Type('string')],
                                        'parentId' => [new Uuid()],
                                        'ruleId' => [new Uuid()],
                                        'position' => [new Type('numeric')],
                                        'trueCase' => [new Type('boolean')],
                                        'displayGroup' => [new Type('numeric')],
                                        'config' => [new Type('array')],
                                    ],
                                ])
                            ),
                        ],
                    ],
                ],
            ]),
        ];
    }
}
