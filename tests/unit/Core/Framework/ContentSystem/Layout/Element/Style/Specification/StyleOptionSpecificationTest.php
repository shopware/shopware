<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StyleOptionSpecification::class)]
class StyleOptionSpecificationTest extends TestCase
{
    #[TestDox('merges value-type schema with adminUI hints and omits name and source when adminUI is present')]
    public function testToSchemaIncludesAdminUiWhenPresent(): void
    {
        $specWithAdminUi = new StyleOptionSpecification(
            'align-self',
            new StyleOptionValueType('string', ['start', 'center', 'end'], null, null, 'start'),
            true,
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
                'breakpointAware' => true,
                'adminUI' => ['component' => 'select'],
            ],
            $specWithAdminUi->toSchema(),
        );
    }

    #[TestDox('keeps the adminUI key in the schema as null when no adminUI block is provided')]
    public function testToSchemaOmitsAdminUiWhenAbsent(): void
    {
        $specWithoutAdminUi = new StyleOptionSpecification(
            'display',
            new StyleOptionValueType('boolean', null, null, null, null),
            false,
            null,
            'core',
        );

        static::assertSame(
            [
                'type' => 'boolean',
                'enum' => null,
                'range' => null,
                'maxLength' => null,
                'default' => null,
                'breakpointAware' => false,
                'adminUI' => null,
            ],
            $specWithoutAdminUi->toSchema(),
        );
    }
}
