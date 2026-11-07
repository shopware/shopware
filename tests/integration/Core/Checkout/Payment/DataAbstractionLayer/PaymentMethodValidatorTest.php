<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Payment\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentMethodValidatorTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testAPaymentMethodWithoutPluginCanBeDeleted(): void
    {
        $this->createPaymentMethod([]);

        static::getContainer()->get('payment_method.repository')->delete([['id' => $this->ids->get('payment-method')]], Context::createDefaultContext());

        $this->expectNotToPerformAssertions();
    }

    public function testAPluginsPaymentMethodCannotBeDeleted(): void
    {
        $this->createPaymentMethod(['plugin' => [
            'name' => 'SwagTestPayment',
            'baseClass' => 'SwagTestPayment\\SwagTestPayment',
            'label' => 'Test payment',
            'version' => '1.0.0',
            'autoload' => [],
        ]]);

        $this->expectExceptionObject(PaymentException::pluginPaymentMethodDeleteRestriction());

        static::getContainer()->get('payment_method.repository')->delete([['id' => $this->ids->get('payment-method')]], Context::createDefaultContext());
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createPaymentMethod(array $values): void
    {
        static::getContainer()->get('payment_method.repository')->create([[
            'id' => $this->ids->get('payment-method'),
            'name' => 'test',
            'technicalName' => 'payment_' . $this->ids->get('payment-method'),
            'handlerIdentifier' => TestPaymentHandler::class,
            ...$values,
        ]], Context::createDefaultContext());
    }
}
