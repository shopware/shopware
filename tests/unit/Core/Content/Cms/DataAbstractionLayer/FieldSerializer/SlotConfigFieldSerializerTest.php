<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\DataAbstractionLayer\Field\SlotConfigField;
use Shopware\Core\Content\Cms\DataAbstractionLayer\FieldSerializer\SlotConfigFieldSerializer;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(SlotConfigFieldSerializer::class)]
class SlotConfigFieldSerializerTest extends TestCase
{
    public function testEncodeUsesSlotConfigFieldSerializerConstraints(): void
    {
        $id = Uuid::randomHex();
        $expected = new All([
            'constraints' => new Collection([
                'allowExtraFields' => false,
                'allowMissingFields' => false,
                'fields' => [
                    'source' => [
                        new Choice([
                            'choices' => [
                                FieldConfig::SOURCE_STATIC,
                                FieldConfig::SOURCE_MAPPED,
                                FieldConfig::SOURCE_PRODUCT_STREAM,
                                FieldConfig::SOURCE_DEFAULT,
                            ],
                        ]),
                        new NotBlank(),
                    ],
                    'value' => [],
                ],
            ]),
        ]);

        $serializer = $this->getSerializer($id, $expected);

        $existence = new EntityExistence(
            'property',
            ['id' => $id],
            true,
            false,
            false,
            []
        );

        $pair = new KeyValuePair('id', $id, false);
        $data = $this->createMock(WriteParameterBag::class);

        $field = new SlotConfigField('id', 'id');
        $serializer->encode($field, $existence, $pair, $data)->current();
    }

    private function getSerializer(string $value, All $expected): SlotConfigFieldSerializer
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator
            ->expects(static::once())
            ->method('validate')
            ->with($value, $expected)
            ->willReturn(new ConstraintViolationList());

        return new SlotConfigFieldSerializer(
            $validator,
            $this->createMock(DefinitionInstanceRegistry::class)
        );
    }
}
