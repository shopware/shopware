<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedByFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CreatedByFieldSerializer::class)]
class CreatedByFieldSerializerTest extends TestCase
{
    private DefinitionInstanceRegistry&MockObject $definitionInstanceRegistry;

    private ValidatorInterface&MockObject $validator;

    private CreatedByFieldSerializer $fieldSerializer;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->fieldSerializer = new CreatedByFieldSerializer(
            $this->validator,
            $this->definitionInstanceRegistry,
        );
    }

    public function testEncode(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = $this->createMock(EntityExistence::class);
        $existence->method('exists')->willReturn(false);
        $userId = Uuid::randomHex();

        $parameters = new WriteParameterBag(
            $this->createMock(EntityDefinition::class),
            $this->createWriteContext($userId),
            '/',
            new WriteCommandQueue(),
        );

        $return = $this->fieldSerializer->encode(
            new CreatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        )->current();

        static::assertSame($userId, Uuid::fromBytesToHex($return));
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
