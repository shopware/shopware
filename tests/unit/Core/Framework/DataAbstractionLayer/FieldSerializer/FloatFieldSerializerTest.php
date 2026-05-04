<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FloatFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FloatFieldSerializer::class)]
#[Group('FieldSerializer')]
#[Group('DAL')]
class FloatFieldSerializerTest extends TestCase
{
    private FloatFieldSerializer $serializer;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $validator = new RecursiveValidator(
            new ExecutionContextFactory($this->createMock(TranslatorInterface::class)),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $this->serializer = new FloatFieldSerializer(
            $validator,
            $this->definitionInstanceRegistry
        );
    }

    public function testChoiceIsNonStrictByDefault(): void
    {
        $field = (new FloatField('test', 'test'))->addFlags(new Choice([1.5]));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 1.7, true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 1.7], $encoded);
    }

    public function testChoiceStrictAcceptsNumericStringMatchingChoice(): void
    {
        $field = (new FloatField('test', 'test'))->addFlags(new Choice([1.5, 2.5], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', '1.5', true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 1.5], $encoded);
    }

    public function testChoiceStrictRejectsInvalidValue(): void
    {
        $field = (new FloatField('test', 'test'))->addFlags(new Choice([1.5, 2.5], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 1.7, true);

        static::expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation('Invalid choice.', 'Invalid choice.', [], null, '/test', 1.7),
            ])
        ));

        iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));
    }

    private function createWriteParameterBag(): WriteParameterBag
    {
        return new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }
}
