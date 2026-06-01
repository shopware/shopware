<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Test\Api\Serializer\AssertValuesTrait;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AssociationExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendableDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ScalarRuntimeExtension;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\SerializationFixture;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicStruct;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithExtension;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithToOneRelationship;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestCollectionWithToOneRelationship;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(JsonEntityEncoder::class)]
class JsonSalesChannelEntityEncoderTest extends TestCase
{
    use AssertValuesTrait;

    private DefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class => ProductDefinition::class,
                MediaDefinition::class => MediaDefinition::class,
                UserDefinition::class => UserDefinition::class,
                ExtendableDefinition::class => ExtendableDefinition::class,
                ExtendedDefinition::class => ExtendedDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    /**
     * @return iterable<string, array<int, bool|\DateTime|float|int|string|null>>
     */
    public static function emptyInputProvider(): iterable
    {
        yield 'empty input null' => [null];
        yield 'empty input string' => ['string'];
        yield 'empty input 1' => [1];
        yield 'empty input false' => [false];
        yield 'empty input date time' => [new \DateTime()];
        yield 'empty input 1 point 1' => [1.1];
    }

    /**
     * @param bool|\DateTime|float|int|string|null $input
     */
    #[DataProvider('emptyInputProvider')]
    public function testEncodeWithEmptyInput(mixed $input): void
    {
        $this->expectExceptionObject(ApiException::unsupportedEncoderInput());

        $encoder = $this->createEncoder();

        $encoder->encode(
            new Criteria(),
            $this->createDefinition(ProductDefinition::class),
            /** @phpstan-ignore argument.type (for test purpose) */
            $input,
            SerializationFixture::SALES_CHANNEL_API_BASE_URL
        );
    }

    /**
     * @return iterable<string, array{class-string<EntityDefinition>, SerializationFixture}>
     */
    public static function complexStructsProvider(): iterable
    {
        yield 'media resource with basic struct is encoded' => [MediaDefinition::class, new TestBasicStruct()];
        yield 'media resource with to one relationship is encoded' => [MediaDefinition::class, new TestBasicWithToOneRelationship()];
        yield 'media resource with collection to one relationship is encoded' => [MediaDefinition::class, new TestCollectionWithToOneRelationship()];
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    #[DataProvider('complexStructsProvider')]
    public function testEncodeComplexStructs(string $definitionClass, SerializationFixture $fixture): void
    {
        $definition = $this->createDefinition($definitionClass);
        $encoder = $this->createEncoder();
        $actual = $encoder->encode(
            new Criteria(),
            $definition,
            $fixture->getInput(),
            SerializationFixture::SALES_CHANNEL_API_BASE_URL
        );

        $this->assertValues($fixture->getSalesChannelJsonFixtures(), $actual);
    }

    public function testEncodeStructWithExtension(): void
    {
        $extendableDefinition = $this->createExtendableDefinitionWithExtensions();
        $fixture = new TestBasicWithExtension();

        $encoder = $this->createEncoder();
        $actual = $encoder->encode(
            new Criteria(),
            $extendableDefinition,
            $fixture->getInput(),
            SerializationFixture::SALES_CHANNEL_API_BASE_URL
        );
        unset($actual['apiAlias']);

        $this->assertValues($fixture->getSalesChannelJsonFixtures(), $actual);
    }

    public function testEncodeStructWithToManyExtension(): void
    {
        $extendableDefinition = $this->createExtendableDefinitionWithExtensions(includeScalarRuntimeExtension: false);
        $fixture = new TestBasicWithExtension();

        $encoder = $this->createEncoder();
        $actual = $encoder->encode(
            new Criteria(),
            $extendableDefinition,
            $fixture->getInput(),
            SerializationFixture::SALES_CHANNEL_API_BASE_URL
        );
        unset($actual['apiAlias']);

        $this->assertValues($fixture->getSalesChannelJsonFixtures(), $actual);
    }

    private function createExtendableDefinitionWithExtensions(bool $includeScalarRuntimeExtension = true): ExtendableDefinition
    {
        $extendableDefinition = new ExtendableDefinition();
        $extendableDefinition->addExtension(new AssociationExtension());

        if ($includeScalarRuntimeExtension) {
            $extendableDefinition->addExtension(new ScalarRuntimeExtension());
        }

        $extendableDefinition->compile($this->definitionRegistry);

        return $extendableDefinition;
    }

    private function createEncoder(): JsonEntityEncoder
    {
        return new JsonEntityEncoder(new Serializer([new StructNormalizer()], [new JsonEncoder()]));
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    private function createDefinition(string $definitionClass): EntityDefinition
    {
        return $this->definitionRegistry->get($definitionClass);
    }
}
