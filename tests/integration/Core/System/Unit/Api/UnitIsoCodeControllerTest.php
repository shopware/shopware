<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Unit\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
class UnitIsoCodeControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testReturnsUnitCodesWithUnitReadPermission(): void
    {
        $browser = $this->getBrowser(permissions: ['unit:read']);

        $browser->request('GET', '/api/_action/unit/iso-codes');

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        $codes = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($codes);
        static::assertContains(['code' => 'KGM', 'name' => 'kilogram'], $codes);
    }

    public function testRejectsRequestWithoutUnitReadPermission(): void
    {
        $browser = $this->getBrowser(permissions: []);

        $browser->request('GET', '/api/_action/unit/iso-codes');

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
    }
}
