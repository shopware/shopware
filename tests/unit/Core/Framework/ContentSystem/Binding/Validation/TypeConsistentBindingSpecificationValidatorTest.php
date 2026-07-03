<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\TypeConsistentBindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\TypeConsistentBindingSpecificationValidator;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @internal
 */
#[CoversClass(TypeConsistentBindingSpecificationValidator::class)]
class TypeConsistentBindingSpecificationValidatorTest extends TestCase
{
    #[TestDox('rethrows a non-client-defect ContentSystemException raised while decoding a resolves config')]
    public function testRethrowsNonClientDefectExceptionFromDecode(): void
    {
        $type = new ContentSystemElementTypeSpecification(
            'Sw:Media:Image',
            'Image',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            ['media' => new PropertySpecification(
                'media',
                new PropertyType(Entity::class, false, null, null),
                false,
                '',
                '',
                null,
            )],
            [],
        );

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('get')->willReturn($type);

        // INVALID_FIELD_TYPE is NOT in CLIENT_DEFECT_CODES, so decodeConfig() must rethrow it rather than
        // turning it into a violation.
        $provider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $provider->method('decode')->willThrowException(ContentSystemException::invalidFieldType('A', 'B'));

        $validator = new TypeConsistentBindingSpecificationValidator(
            $registry,
            $provider,
            static::createStub(RootContextMapper::class),
        );
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $dto = new BindingSpecificationDto(
            type: 'Sw:Media:Image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => []]],
            inputs: [],
        );

        try {
            $validator->validate($dto, new TypeConsistentBindingSpecification());
            static::fail('Expected a ContentSystemException to be rethrown.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::INVALID_FIELD_TYPE, $e->getErrorCode());
        }
    }
}
