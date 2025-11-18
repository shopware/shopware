<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedByFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(UpdatedByFieldSerializer::class)]
class UpdatedByFieldSerializerTest extends TestCase
{
    private DefinitionInstanceRegistry&MockObject $definitionInstanceRegistry;

    private ValidatorInterface&MockObject $validator;

    private UpdatedByFieldSerializer $fieldSerializer;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->fieldSerializer = new UpdatedByFieldSerializer(
            $this->validator,
            $this->definitionInstanceRegistry,
        );
    }

    public function testEncode(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(true);
        $userId = Uuid::randomHex();

        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext($userId),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertSame($userId, Uuid::fromBytesToHex($result['updated_by_id'] ?? ''));
    }

    public function testEncodeWithInvalidField(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext(null),
            '/',
            new WriteCommandQueue(),
        );

        $wrongField = new JsonField('key', 'key');

        $this->expectExceptionObject(DataAbstractionLayerException::invalidSerializerField(UpdatedByField::class, $wrongField));

        $this->fieldSerializer->encode(
            $wrongField,
            $existence,
            $data,
            $parameters
        )->current();
    }

    public function testEncodeWithoutExistingEntity(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(false);
        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext(Uuid::randomHex()),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertEmpty($result);
    }

    public function testEncodeWithInvalidScope(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(true);

        $result = 'foo';
        Context::createDefaultContext()->scope('invalid-scope', function (Context $context) use ($data, $existence, &$result): void {
            $result = $this->fieldSerializer->encode(
                new UpdatedByField([Context::USER_SCOPE]),
                $existence,
                $data,
                new WriteParameterBag(
                    $this->createMock(EntityDefinition::class),
                    WriteContext::createFromContext($context),
                    '/',
                    new WriteCommandQueue(),
                )
            )->current();
        });

        static::assertNull($result);
    }

    public function testEncodeWithSalesChannelApiSource(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(true);
        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext(null, Defaults::LIVE_VERSION, false),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertEmpty($result);
    }

    /**
     * @deprecated tag:v6.8.0 - remove this test, as the behavior will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEncodeWithNoUserIdDeprecated(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(true);
        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext(null),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertEmpty($result);
    }

    public function testEncodeWithNoUserId(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(true);
        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext(null),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertSame(['updated_by_id' => null], $result);
    }

    private function createWriteContext(?string $userId, string $versionId = Defaults::LIVE_VERSION, bool $useAdminApiSource = true): WriteContext
    {
        if ($useAdminApiSource) {
            $source = new AdminApiSource($userId);
        } else {
            $source = new SalesChannelApiSource(TestDefaults::SALES_CHANNEL);
        }

        $context = Context::createDefaultContext($source)->createWithVersionId($versionId);

        return WriteContext::createFromContext($context);
    }
}
