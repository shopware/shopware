<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\Executor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeCurrencyCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingAddressCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingLocationCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingMethodCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopware\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandValidator;
use Shopware\Core\Framework\Gateway\Context\Command\LoginCustomerCommand;
use Shopware\Core\Framework\Gateway\Context\Command\RegisterCustomerCommand;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextGatewayCommandValidator::class)]
class ContextGatewayCommandValidatorTest extends TestCase
{
    public function testValidateWithMaximumCommands(): void
    {
        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->never())
            ->method(static::anything());

        $validator = new ContextGatewayCommandValidator($logger);

        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(RegisterCustomerCommand::class, ['data' => ['foo' => 'bar']]));
        $commands->add(self::getCommand(ChangeBillingAddressCommand::class, ['addressId' => '123']));
        $commands->add(self::getCommand(ChangeCurrencyCommand::class, ['iso' => 'EUR']));
        $commands->add(self::getCommand(ChangeLanguageCommand::class, ['iso' => 'de_DE']));
        $commands->add(self::getCommand(ChangePaymentMethodCommand::class, ['technicalName' => 'test_app_payment']));
        $commands->add(self::getCommand(ChangeShippingAddressCommand::class, ['addressId' => '123']));
        $commands->add(self::getCommand(ChangeShippingLocationCommand::class, ['countryIso' => 'DE', 'countryStateIso' => 'DE-BY']));
        $commands->add(self::getCommand(ChangeShippingMethodCommand::class, ['technicalName' => 'test_app_shipping']));

        $validator->validate($commands, Generator::generateSalesChannelContext());
    }

    public function testValidateWithMultipleRegisterCommands(): void
    {
        $expectedException = GatewayException::commandValidationFailed('Only one register or login command is allowed');

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->once())
            ->method('logOrThrowException')
            ->with($expectedException);

        $validator = new ContextGatewayCommandValidator($logger);

        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(RegisterCustomerCommand::class, ['data' => ['foo' => 'bar']]));
        $commands->add(self::getCommand(RegisterCustomerCommand::class, ['data' => ['foo' => 'bar']]));

        $validator->validate($commands, Generator::generateSalesChannelContext());
    }

    public function testValidateWithMultipleLoginCommands(): void
    {
        $expectedException = GatewayException::commandValidationFailed('Only one register or login command is allowed');

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->once())
            ->method('logOrThrowException')
            ->with($expectedException);

        $validator = new ContextGatewayCommandValidator($logger);

        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(LoginCustomerCommand::class, ['customerEmail' => 'foo@bar.com']));
        $commands->add(self::getCommand(LoginCustomerCommand::class, ['customerEmail' => 'foo@bar.com']));

        $validator->validate($commands, Generator::generateSalesChannelContext());
    }

    public function testValidateWithLoginAndRegisterCommands(): void
    {
        $expectedException = GatewayException::commandValidationFailed('Only one register or login command is allowed');

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->once())
            ->method('logOrThrowException')
            ->with($expectedException);

        $validator = new ContextGatewayCommandValidator($logger);

        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(RegisterCustomerCommand::class, ['data' => ['foo' => 'bar']]));
        $commands->add(self::getCommand(LoginCustomerCommand::class, ['customerEmail' => 'foo@bar.com']));

        $validator->validate($commands, Generator::generateSalesChannelContext());
    }

    public function testValidateWithDuplicateCommands(): void
    {
        $expectedException = GatewayException::commandValidationFailed('Duplicate commands of a type are not allowed');

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->once())
            ->method('logOrThrowException')
            ->with($expectedException);

        $validator = new ContextGatewayCommandValidator($logger);

        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(ChangeCurrencyCommand::class, ['iso' => 'EUR']));
        $commands->add(self::getCommand(ChangeCurrencyCommand::class, ['iso' => 'USD']));

        $validator->validate($commands, Generator::generateSalesChannelContext());
    }

    /**
     * @template T of AbstractContextGatewayCommand
     *
     * @param class-string<T> $type
     * @param array<string, mixed> $data
     */
    private static function getCommand(string $type, array $data = []): AbstractContextGatewayCommand
    {
        return $type::createFromPayload($data);
    }
}
