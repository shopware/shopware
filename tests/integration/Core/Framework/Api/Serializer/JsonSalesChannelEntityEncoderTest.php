<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\Api\Serializer\AssertValuesTrait;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\SerializationFixture;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicStruct;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicWithExtension;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicWithToOneRelationship;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestCollectionWithToOneRelationship;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AssociationExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendableDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ScalarRuntimeExtension;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class JsonSalesChannelEntityEncoderTest extends TestCase
{
    use AssertValuesTrait;
    use DataAbstractionLayerFieldTestBehaviour;
    use KernelTestBehaviour;

    /**
     * @return array<int, array<int, bool|\DateTime|float|int|string|null>>
     */
    public static function emptyInputProvider(): array
    {
        return [
            [null],
            ['string'],
            [1],
            [false],
            [new \DateTime()],
            [1.1],
        ];
    }

    /**
     * @param bool|\DateTime|float|int|string|null $input
     */
    #[DataProvider('emptyInputProvider')]
    public function testEncodeWithEmptyInput(mixed $input): void
    {
        if (Feature::isActive('v6.7.0.0')) {
            $this->expectException(ApiException::class);
        } else {
            $this->expectException(UnsupportedEncoderInputException::class);
        }
        $this->expectExceptionMessage('Unsupported encoder data provided. Only entities and entity collections are supported');

        $encoder = static::getContainer()->get(JsonEntityEncoder::class);

        $encoder->encode(
            new Criteria(),
            static::getContainer()->get(ProductDefinition::class),
            /** @phpstan-ignore-next-line intentionally wrong parameter provided **/
            $input,
            SerializationFixture::SALES_CHANNEL_API_BASE_URL
        );
    }

    /**
     * @return list<array{class-string<EntityDefinition>, SerializationFixture}>
     */
    public static function complexStructsProvider(): array
    {
        return [
            [MediaDefinition::class, new TestBasicStruct()],
            [MediaDefinition::class, new TestBasicWithToOneRelationship()],
            [MediaDefinition::class, new TestCollectionWithToOneRelationship()],
        ];
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    #[DataProvider('complexStructsProvider')]
    public function testEncodeComplexStructs(string $definitionClass, SerializationFixture $fixture): void
    {
        $definition = static::getContainer()->get($definitionClass);
        static::assertInstanceOf(EntityDefinition::class, $definition);
        $encoder = static::getContainer()->get(JsonEntityEncoder::class);
        $actual = $encoder->encode(
            new Criteria(),
            $definition,
            $fixture->getInput(),
            SerializationFixture::SALES_CHANNEL_API_BASE_URL
        );

        $this->assertValues($fixture->getSalesChannelJsonFixtures(), $actual);
    }

    /**
     * Not possible with data provider as we have to manipulate the container, but the data provider run before all tests
     */
    public function testEncodeStructWithExtension(): void
    {
        $this->registerDefinition(ExtendableDefinition::class, ExtendedDefinition::class);
        $extendableDefinition = new ExtendableDefinition();
        $extendableDefinition->addExtension(new AssociationExtension());
        $extendableDefinition->addExtension(new ScalarRuntimeExtension());

        $extendableDefinition->compile(static::getContainer()->get(DefinitionInstanceRegistry::class));
        $fixture = new TestBasicWithExtension();

        $encoder = static::getContainer()->get(JsonEntityEncoder::class);
        $actual = $encoder->encode(
            new Criteria(),
            $extendableDefinition,
            $fixture->getInput(),
            SerializationFixture::SALES_CHANNEL_API_BASE_URL
        );
        unset($actual['apiAlias']);

        $this->assertValues($fixture->getSalesChannelJsonFixtures(), $actual);
    }

    /**
     * Not possible with data provider as we have to manipulate the container, but the data provider run before all tests
     */
    public function testEncodeStructWithToManyExtension(): void
    {
        $this->registerDefinition(ExtendableDefinition::class, ExtendedDefinition::class);
        $extendableDefinition = new ExtendableDefinition();
        $extendableDefinition->addExtension(new AssociationExtension());

        $extendableDefinition->compile(static::getContainer()->get(DefinitionInstanceRegistry::class));
        $fixture = new TestBasicWithExtension();

        $encoder = static::getContainer()->get(JsonEntityEncoder::class);
        $actual = $encoder->encode(
            new Criteria(),
            $extendableDefinition,
            $fixture->getInput(),
            SerializationFixture::SALES_CHANNEL_API_BASE_URL
        );
        unset($actual['apiAlias']);

        $this->assertValues($fixture->getSalesChannelJsonFixtures(), $actual);
    }
}
