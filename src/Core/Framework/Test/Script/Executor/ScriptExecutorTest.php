<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Executor;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Script\Executor\ScriptExecutor;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ScriptExecutorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    private ScriptExecutor $executor;

    public function setUp(): void
    {
        $this->executor = $this->getContainer()->get(ScriptExecutor::class);
    }

    public function testExecute(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Registry/_fixtures/apps/test');

        $testObject = new TestContextObject();

        $this->executor->execute(
            'product-page-loaded',
            ['testObject' => $testObject]
        );

        static::assertTrue($testObject->wasCalled());
    }
}
