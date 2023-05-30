<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Content\Flow\DataAbstractionLayer\Field\FlowTemplateConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('business-ops')]
class FlowTemplateConfigFieldSerializer extends JsonFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof FlowTemplateConfigField) {
            throw DataAbstractionLayerException::invalidSerializerField(FlowTemplateConfigField::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();

        if (!\is_array($value)) {
            yield $field->getStorageName() => null;

            return;
        }

        $value = array_merge([
            'description' => null,
            'sequences' => [],
        ], $value);

        $sequences = $value['sequences'];

        $value['sequences'] = array_map(fn ($item) => array_merge([
            'parentId' => null,
            'ruleId' => null,
            'position' => 1,
            'displayGroup' => 1,
            'trueCase' => 0,
        ], $item), $sequences);

        yield $field->getStorageName() => Json::encode($value);
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
