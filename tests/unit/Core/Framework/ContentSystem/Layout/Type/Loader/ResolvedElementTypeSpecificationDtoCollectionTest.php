<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ResolvedElementTypeSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ResolvedElementTypeSpecificationDtoCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\CopilotSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDto;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ResolvedElementTypeSpecificationDtoCollection::class)]
class ResolvedElementTypeSpecificationDtoCollectionTest extends TestCase
{
    #[TestDox('validate does not throw when all DTOs are valid')]
    public function testValidatePassesForValidDtos(): void
    {
        $batch = new ResolvedElementTypeSpecificationDtoCollection([
            $this->buildResolved('Sw:Alpha', 'core'),
            $this->buildResolved('Sw:Beta', 'core'),
        ]);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $batch->validate($validator);

        $this->expectNotToPerformAssertions();
    }

    #[TestDox('validate throws with prefixed property paths when DTOs have violations')]
    public function testValidateThrowsWithPrefixedPaths(): void
    {
        $batch = new ResolvedElementTypeSpecificationDtoCollection([
            $this->buildResolved('Sw:Bad:A', 'core'),
            $this->buildResolved('Sw:Bad:B', 'core'),
        ]);

        $violations = new ConstraintViolationList([
            new ConstraintViolation('must not be blank', null, [], null, 'label', ''),
        ]);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn($violations);

        $this->expectExceptionObject(ContentSystemException::elementTypesInvalid(
            new ConstraintViolationList([
                new ConstraintViolation('must not be blank', null, [], null, '[Sw:Bad:A].label', ''),
                new ConstraintViolation('must not be blank', null, [], null, '[Sw:Bad:B].label', ''),
            ])
        ));
        $batch->validate($validator);
    }

    #[TestDox('toSpecifications converts all items to value objects')]
    public function testToSpecificationsConvertsAllItems(): void
    {
        $batch = new ResolvedElementTypeSpecificationDtoCollection([
            $this->buildResolved('Sw:Alpha', 'core'),
            $this->buildResolved('Sw:Beta', 'plugin:MyPlugin'),
        ]);

        $specs = $batch->toSpecifications();

        static::assertCount(2, $specs);
        static::assertSame('Sw:Alpha', $specs[0]->name());
        static::assertSame('core', $specs[0]->source());
        static::assertSame('Sw:Beta', $specs[1]->name());
        static::assertSame('plugin:MyPlugin', $specs[1]->source());
    }

    #[TestDox('toSpecifications returns empty list for empty collection')]
    public function testToSpecificationsReturnsEmptyForEmptyCollection(): void
    {
        $batch = new ResolvedElementTypeSpecificationDtoCollection([]);

        static::assertSame([], $batch->toSpecifications());
    }

    private function buildResolved(string $name, string $source): ResolvedElementTypeSpecificationDto
    {
        $dto = new ElementTypeSpecificationDto(
            label: 'Test',
            description: 'A test element.',
            vendor: 'shopware AG',
            icon: null,
            category: null,
            copilot: new CopilotSpecificationDto('Test element.', []),
            properties: [],
            slots: [],
        );

        return new ResolvedElementTypeSpecificationDto($name, $source, $dto);
    }
}
