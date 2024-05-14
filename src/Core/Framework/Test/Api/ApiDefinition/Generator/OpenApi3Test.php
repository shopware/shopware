<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
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

    private static string $env = 'test';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$env = $_SERVER['APP_ENV'] ?? 'test';
        $_SERVER['APP_ENV'] = 'prod';

        KernelLifecycleManager::ensureKernelShutdown();
        KernelLifecycleManager::bootKernel();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        $_SERVER['APP_ENV'] = self::$env;
        KernelLifecycleManager::ensureKernelShutdown();
    }

    public function testRequestOpenApi3Json(): void
    {
        $infoController = KernelLifecycleManager::getKernel()->getContainer()->get(StoreApiInfoController::class);

        $response = $infoController->info(new Request());

        static::assertSame(200, $response->getStatusCode(), print_r($response->getContent(), true));
    }
}
