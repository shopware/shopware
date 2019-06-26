<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\SerializationFixture;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicStruct;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicWithToManyRelationships;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestBasicWithToOneRelationship;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestCollectionWithSelfReference;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestCollectionWithToOneRelationship;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestInternalFieldsAreFiltered;
use Shopware\Core\Framework\Test\Api\Serializer\fixtures\TestMainResourceShouldNotBeInIncluded;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\User\UserDefinition;

class JsonSalesChannelEntityEncoderTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var JsonApiEncoder
     */
    private $encoder;

    protected function setUp(): void
    {
        $this->encoder = new JsonEntityEncoder($this->getContainer()->get('serializer'));
    }

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

        $this->encoder->encode($this->getContainer()->get(ProductDefinition::class), $input, SerializationFixture::SALES_CHANNEL_API_BASE_URL);
    }

    public function complexStructsProvider(): array
    {
        return [
            [$this->getContainer()->get(MediaDefinition::class), new TestBasicStruct()],
            [$this->getContainer()->get(UserDefinition::class), new TestBasicWithToManyRelationships()],
            [$this->getContainer()->get(MediaDefinition::class), new TestBasicWithToOneRelationship()],
            [$this->getContainer()->get(MediaFolderDefinition::class), new TestCollectionWithSelfReference()],
            [$this->getContainer()->get(MediaDefinition::class), new TestCollectionWithToOneRelationship()],
            [$this->getContainer()->get(RuleDefinition::class), new TestInternalFieldsAreFiltered()],
            [$this->getContainer()->get(UserDefinition::class), new TestMainResourceShouldNotBeInIncluded()],
        ];
    }

    /**
     * @dataProvider complexStructsProvider
     */
    public function testEncodeComplexStructs(EntityDefinition $definition, SerializationFixture $fixture): void
    {
        $actual = $this->encoder->encode($definition, $fixture->getInput(), SerializationFixture::SALES_CHANNEL_API_BASE_URL);

        static::assertEquals($fixture->getSalesChannelJsonFixtures(), $actual);
    }
}
