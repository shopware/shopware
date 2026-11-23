<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Unit\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Unit\Api\UnitUneceCodeController;
use Shopware\Core\System\Unit\UnitUneceCodes;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(UnitUneceCodeController::class)]
class UnitUneceCodeControllerTest extends TestCase
{
    public function testReturnsCodesAsListOfCodeAndName(): void
    {
        $response = (new UnitUneceCodeController(new UnitUneceCodes()))->getUneceCodes();

        $codes = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertIsArray($codes);
        static::assertTrue(array_is_list($codes));
        static::assertContains(['code' => 'KGM', 'name' => 'kilogram'], $codes);
        static::assertContains(['code' => 'XBX', 'name' => 'box'], $codes);
    }

    public function testNumericCodesStayStrings(): void
    {
        $response = (new UnitUneceCodeController(new UnitUneceCodes()))->getUneceCodes();

        $codes = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertIsArray($codes);
        static::assertContains(['code' => '10', 'name' => 'group'], $codes);
    }
}
