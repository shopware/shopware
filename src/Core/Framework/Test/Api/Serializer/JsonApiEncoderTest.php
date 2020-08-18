<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\SerializationFixture;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicStruct;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicWithExtension;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicWithToManyExtension;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicWithToManyRelationships;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicWithToOneRelationship;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestCollectionWithSelfReference;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestCollectionWithToOneRelationship;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestInternalFieldsAreFiltered;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestMainResourceShouldNotBeInIncluded;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AssociationExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendableDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ScalarRuntimeExtension;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\User\UserDefinition;

class JsonApiEncoderTest extends TestCase
{
    use KernelTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    public function emptyInputProvider(): array
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
     * @dataProvider emptyInputProvider
     */
    public function testEncodeWithEmptyInput($input): void
    {
        $this->expectException(UnsupportedEncoderInputException::class);

        $encoder = $this->getContainer()->get(JsonApiEncoder::class);
        $encoder->encode(new Criteria(), $this->getContainer()->get(ProductDefinition::class), $input, SerializationFixture::API_BASE_URL, SerializationFixture::API_VERSION);
    }

    public function complexStructsProvider(): array
    {
        return [
            [MediaDefinition::class, new TestBasicStruct()],
            [UserDefinition::class, new TestBasicWithToManyRelationships()],
            [MediaDefinition::class, new TestBasicWithToOneRelationship()],
            [MediaFolderDefinition::class, new TestCollectionWithSelfReference()],
            [MediaDefinition::class, new TestCollectionWithToOneRelationship()],
            [RuleDefinition::class, new TestInternalFieldsAreFiltered()],
            [UserDefinition::class, new TestMainResourceShouldNotBeInIncluded()],
        ];
    }

    /**
     * @dataProvider complexStructsProvider
     */
    public function testEncodeComplexStructs(string $definitionClass, SerializationFixture $fixture): void
    {
        /** @var EntityDefinition $definition */
        $definition = $this->getContainer()->get($definitionClass);
        $encoder = $this->getContainer()->get(JsonApiEncoder::class);
        $actual = $encoder->encode(new Criteria(), $definition, $fixture->getInput(), SerializationFixture::API_BASE_URL, SerializationFixture::API_VERSION);
        $actual = json_decode($actual, true);

        // remove extensions from test
        $actual = $this->arrayRemove($actual, 'extensions');
        $actual['included'] = $this->removeIncludedExtensions($actual['included']);

        static::assertEquals($fixture->getAdminJsonApiFixtures(), $actual);
    }

    /**
     * Not possible with dataprovider
     * as we have to manipulate the container, but the dataprovider run before all tests
     */
    public function testEncodeStructWithExtension(): void
    {
        $this->registerDefinition(ExtendableDefinition::class, ExtendedDefinition::class);
        $extendableDefinition = new ExtendableDefinition();
        $extendableDefinition->addExtension(new AssociationExtension());
        $extendableDefinition->addExtension(new ScalarRuntimeExtension());

        $extendableDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));
        $fixture = new TestBasicWithExtension();

        $encoder = $this->getContainer()->get(JsonApiEncoder::class);
        $actual = $encoder->encode(new Criteria(), $extendableDefinition, $fixture->getInput(), SerializationFixture::API_BASE_URL, SerializationFixture::API_VERSION);

        // check that empty "links" object is an object and not array: https://jsonapi.org/format/#document-links
        static::assertStringNotContainsString('"links":[]', $actual);
        static::assertStringContainsString('"links":{}', $actual);

        static::assertEquals($fixture->getAdminJsonApiFixtures(), json_decode($actual, true));
    }

    /**
     * Not possible with dataprovider
     * as we have to manipulate the container, but the dataprovider run before all tests
     */
    public function testEncodeStructWithToManyExtension(): void
    {
        $this->registerDefinition(ExtendableDefinition::class, ExtendedDefinition::class);
        $extendableDefinition = new ExtendableDefinition();
        $extendableDefinition->addExtension(new AssociationExtension());

        $extendableDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));
        $fixture = new TestBasicWithToManyExtension();

        $encoder = $this->getContainer()->get(JsonApiEncoder::class);
        $actual = $encoder->encode(new Criteria(), $extendableDefinition, $fixture->getInput(), SerializationFixture::API_BASE_URL, SerializationFixture::API_VERSION);

        // check that empty "links" object is an object and not array: https://jsonapi.org/format/#document-links
        static::assertStringNotContainsString('"links":[]', $actual);
        static::assertStringContainsString('"links":{}', $actual);

        // check that empty "attributes" object is an object and not array: https://jsonapi.org/format/#document-resource-object-attributes
        static::assertStringNotContainsString('"attributes":[]', $actual);
        static::assertStringContainsString('"attributes":{}', $actual);

        static::assertEquals($fixture->getAdminJsonApiFixtures(), json_decode($actual, true));
    }

    private function arrayRemove($haystack, string $keyToRemove): array
    {
        foreach ($haystack as $key => $value) {
            if (is_array($value)) {
                $haystack[$key] = $this->arrayRemove($haystack[$key], $keyToRemove);
            }

            if ($key === $keyToRemove) {
                unset($haystack[$key]);
            }
        }

        return $haystack;
    }

    private function removeIncludedExtensions($array): array
    {
        $filtered = [];
        foreach ($array as $item) {
            if ($item['type'] !== 'extension') {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }
}
