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
    #[TestDox('merges the value-type schema with the adminUI hints, omits name and source, and keeps the adminUI key present even when null')]
    public function testToSchemaMergesValueTypeAndAdminUi(): void
    {
        $specWithAdminUi = new StyleOptionSpecification(
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
            $specWithAdminUi->toSchema(),
        );

        $specWithoutAdminUi = new StyleOptionSpecification(
            'display',
            new StyleOptionValueType('boolean', null, null, null, null),
            null,
            'core',
        );

        $schema = $specWithoutAdminUi->toSchema();

        static::assertArrayHasKey('adminUI', $schema);
        static::assertNull($schema['adminUI']);
    }
}
