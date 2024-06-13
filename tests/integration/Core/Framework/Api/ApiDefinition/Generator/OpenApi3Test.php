<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannel\StoreApiInfoController;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Group('skip-paratest')]
class OpenApi3Test extends TestCase
{
    use KernelTestBehaviour;

    public function testRequestOpenApi3Json(): void
    {
        $response = self::getContainer()->get(StoreApiInfoController::class)->info(new Request());

        static::assertSame(200, $response->getStatusCode(), print_r($response->getContent(), true));
    }
}
