<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VariantListingConfigField;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('core')]
class VariantListingConfigFieldSerializer extends AbstractFieldSerializer
{
    /**
     * @internal
     */
    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        ValidatorInterface $validator
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof VariantListingConfigField) {
            throw DataAbstractionLayerException::invalidSerializerField(VariantListingConfigField::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();
        $value['displayParent'] = isset($value['displayParent']) ? (int) $value['displayParent'] : null;

        yield $field->getStorageName() => !empty($value) ? Json::encode($value) : null;
    }

    public function decode(Field $field, mixed $value): ?VariantListingConfig
    {
        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        return new VariantListingConfig(
            isset($value['displayParent']) ? (bool) $value['displayParent'] : null,
            $value['mainVariantId'] ?? null,
            $value['configuratorGroupConfig'] ?? null,
        );
    }

    protected function getConstraints(Field $field): array
    {
        return [
            new Collection([
                'allowExtraFields' => true,
                'allowMissingFields' => true,
                'fields' => [
                    'displayParent' => [new Type('boolean')],
                    'mainVariantId' => [new Uuid()],
                    'configuratorGroupConfig' => [
                        new Optional(
                            new Collection([
                                'allowExtraFields' => true,
                                'allowMissingFields' => true,
                                'fields' => [
                                    'id' => [new NotBlank(), new Uuid()],
                                    'representation' => [new NotBlank(), new Type('string')],
                                    'expressionForListings' => [new NotBlank(), new Type('boolean')],
                                ],
                            ])
                        ),
                    ],
                ],
            ]),
        ];
    }
}
