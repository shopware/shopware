<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Event\Hooks\DocumentGenerationHook;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentGenerationHookTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    public function testAppScriptExtendsTheOrder(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/apps/documentDataExample');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $hook = new DocumentGenerationHook(
            $order,
            DocumentType::INVOICE->value,
            '1001',
            [DocumentFormat::PDF->value],
            Context::createDefaultContext(),
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        $extension = $order->getExtension('document_data_example');

        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame(DocumentType::INVOICE->value, $extension->get('documentType'));
        static::assertSame('1001', $extension->get('documentNumber'));
    }
}
