<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Faker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Demodata\Faker\Commerce;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

/**
 * @internal
 */
#[CoversClass(Commerce::class)]
class CommerceTest extends TestCase
{
    public function testCustomFieldSet(): void
    {
        $commerce = new Commerce(Factory::create());

        ReflectionHelper::getProperty(Commerce::class, 'productName')->setValue($commerce, ['adjective' => ['Test Product Name']]);

        $setName = $commerce->customFieldSet();

        static::assertStringNotContainsString(' ', $setName);
        static::assertStringContainsString('Test_Product_Name', $setName);
    }
}
