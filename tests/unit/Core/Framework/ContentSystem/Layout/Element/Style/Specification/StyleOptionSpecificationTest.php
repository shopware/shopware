<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;

/**
 * @internal
 */
#[CoversClass(StyleOptionSpecification::class)]
class StyleOptionSpecificationTest extends TestCase
{
    #[TestDox('exposes the wire-key name and its source label')]
    public function testExposesNameAndSource(): void
    {
        $spec = new StyleOptionSpecification(
            'col-span',
            new StyleOptionValueType('integer', null, ['min' => 1, 'max' => 12], null, null),
            null,
            'core',
        );

        static::assertSame('col-span', $spec->name());
        static::assertSame('core', $spec->source());
    }

    #[TestDox('source defaults to empty when not supplied')]
    public function testSourceDefaultsToEmpty(): void
    {
        $spec = new StyleOptionSpecification(
            'display',
            new StyleOptionValueType('boolean', null, null, null, null),
            null,
        );

        static::assertSame('', $spec->source());
    }

    #[TestDox('toSchema merges the value-type schema with the adminUI hints and omits name and source')]
    public function testToSchemaMergesValueTypeAndAdminUi(): void
    {
        $spec = new StyleOptionSpecification(
            'align-self',
            new StyleOptionValueType('string', ['start', 'center', 'end'], null, null, 'start'),
            ['component' => 'select'],
            'core',
        );

        static::assertSame(
            [
                'type' => 'string',
                'enum' => ['start', 'center', 'end'],
                'range' => null,
                'maxLength' => StyleOptionValueType::DEFAULT_STRING_MAX_LENGTH,
                'default' => 'start',
                'adminUI' => ['component' => 'select'],
            ],
            $spec->toSchema(),
        );
    }

    #[TestDox('toSchema keeps the adminUI key present as null when no hints are declared')]
    public function testToSchemaKeepsNullAdminUi(): void
    {
        $spec = new StyleOptionSpecification(
            'display',
            new StyleOptionValueType('boolean', null, null, null, null),
            null,
            'core',
        );

        $schema = $spec->toSchema();

        static::assertArrayHasKey('adminUI', $schema);
        static::assertNull($schema['adminUI']);
    }
}
