<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
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
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithToManyExtension;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithToOneRelationship;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestCollectionWithToOneRelationship;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(JsonApiEncoder::class)]
class JsonSalesChannelApiEncoderTest extends TestCase
{
    use AssertValuesTrait;

    private DefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class => ProductDefinition::class,
                MediaDefinition::class => MediaDefinition::class,
                MediaThumbnailDefinition::class => MediaThumbnailDefinition::class,
                MediaThumbnailSizeDefinition::class => MediaThumbnailSizeDefinition::class,
                MediaTranslationDefinition::class => MediaTranslationDefinition::class,
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

        $encoder = new JsonApiEncoder();
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
        $encoder = new JsonApiEncoder();
        $actual = $encoder->encode(new Criteria(), $definition, $fixture->getInput(), SerializationFixture::SALES_CHANNEL_API_BASE_URL);

        $actual = json_decode($actual, true, 512, \JSON_THROW_ON_ERROR);

        // remove extensions from test
        $actual = $this->arrayRemove($actual, 'extensions');
        $actual['included'] = $this->removeIncludedExtensions($actual['included']);

        $this->assertValues($fixture->getSalesChannelJsonApiFixtures(), $actual);
    }

    public function testEncodeStructWithExtension(): void
    {
        $extendableDefinition = $this->createExtendableDefinitionWithExtensions();
        $fixture = new TestBasicWithExtension();

        $encoder = new JsonApiEncoder();
        $actual = $encoder->encode(new Criteria(), $extendableDefinition, $fixture->getInput(), SerializationFixture::SALES_CHANNEL_API_BASE_URL);

        // check that empty "links" object is an object and not array: https://jsonapi.org/format/#document-links
        static::assertStringNotContainsString('"links":[]', $actual);

        // TODO: WTF? Why does it now have a self link
        // static::assertStringContainsString('"links":{}', $actual);

        $this->assertValues($fixture->getSalesChannelJsonApiFixtures(), json_decode($actual, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testEncodeStructWithToManyExtension(): void
    {
        $extendableDefinition = $this->createExtendableDefinitionWithExtensions(includeScalarRuntimeExtension: false);
        $fixture = new TestBasicWithToManyExtension();

        $encoder = new JsonApiEncoder();
        $actual = $encoder->encode(new Criteria(), $extendableDefinition, $fixture->getInput(), SerializationFixture::SALES_CHANNEL_API_BASE_URL);

        // check that empty "links" object is an object and not array: https://jsonapi.org/format/#document-links
        static::assertStringNotContainsString('"links":[]', $actual);
        static::assertStringContainsString('"links":{}', $actual);

        // check that empty "attributes" object is an object and not array: https://jsonapi.org/format/#document-resource-object-attributes
        static::assertStringNotContainsString('"attributes":[]', $actual);
        static::assertStringContainsString('"attributes":{}', $actual);

        $this->assertValues($fixture->getSalesChannelJsonApiFixtures(), json_decode($actual, true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<mixed> $haystack
     *
     * @return array<mixed>
     */
    private function arrayRemove(array $haystack, string $keyToRemove): array
    {
        foreach ($haystack as $key => $value) {
            if (\is_array($value)) {
                $haystack[$key] = $this->arrayRemove($value, $keyToRemove);
            }

            if ($key === $keyToRemove) {
                unset($haystack[$key]);
            }
        }

        return $haystack;
    }

    /**
     * @param array<array<mixed>> $array
     *
     * @return array<array<mixed>>
     */
    private function removeIncludedExtensions(array $array): array
    {
        $filtered = [];
        foreach ($array as $item) {
            if ($item['type'] !== 'extension') {
                $filtered[] = $item;
            }
        }

        return $filtered;
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

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    private function createDefinition(string $definitionClass): EntityDefinition
    {
        return $this->definitionRegistry->get($definitionClass);
    }
}
