<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Faker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Demodata\Faker\Commerce;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Commerce::class)]
class CommerceTest extends TestCase
{
    public function testCustomFieldSet(): void
    {
        $commerce = new Commerce(Factory::create());

        $setNames = [];
        for ($i = 0; $i < 500; ++$i) {
            $setNames[] = $commerce->customFieldSet();
        }

        foreach ($setNames as $setName) {
            static::assertMatchesRegularExpression('/^[A-Za-z_]+_\d+$/D', $setName);
        }

        // "Heavy Duty" is the only adjective with a space; 500 draws out of 17 adjectives practically always hit it
        static::assertNotEmpty(array_filter($setNames, static fn (string $setName): bool => str_starts_with($setName, 'Heavy_Duty_')));
    }
}
