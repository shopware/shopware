<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Struct::class)]
class StructTest extends TestCase
{
    public function testGetApiAliasConvertsTheClassNameToSnakeCase(): void
    {
        static::assertSame(
            'shopware_tests_unit_core_framework_struct_api_alias_example_struct',
            (new ApiAliasExampleStruct())->getApiAlias()
        );
    }
}

/**
 * @internal
 */
class ApiAliasExampleStruct extends Struct
{
}
