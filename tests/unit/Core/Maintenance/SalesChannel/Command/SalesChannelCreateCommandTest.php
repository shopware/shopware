<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\SalesChannel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelCreateCommand;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelCreateCommand::class)]
class SalesChannelCreateCommandTest extends TestCase
{
    /**
     * @param array<string, string> $options
     */
    #[DataProvider('dataProviderTestExecuteSuccess')]
    public function testExecuteSuccess(array $options): void
    {
        $accessKey = AccessKeyHelper::generateAccessKey('sales-channel');

        $passedArguments = [];

        $salesChannelCreator = $this->createMock(SalesChannelCreator::class);
        $salesChannelCreator->expects($this->once())
            ->method('createSalesChannel')
            ->willReturnCallback(function (
                string $id,
                string $name,
                string $typeId,
                ?string $languageId,
                ?string $currencyId,
                ?string $paymentMethodId,
                ?string $shippingMethodId,
                ?string $countryId,
                ?string $customerGroupId,
                ?string $navigationCategoryId
            ) use (&$passedArguments, $accessKey): string {
                $passedArguments = [
                    'id' => $id,
                    'name' => $name,
                    'typeId' => $typeId,
                    'languageId' => $languageId,
                    'currencyId' => $currencyId,
                    'paymentMethodId' => $paymentMethodId,
                    'shippingMethodId' => $shippingMethodId,
                    'countryId' => $countryId,
                    'customerGroupId' => $customerGroupId,
                    'navigationCategoryId' => $navigationCategoryId,
                ];

                return $accessKey;
            });

        $commandTester = new CommandTester(new SalesChannelCreateCommand($salesChannelCreator));

        static::assertSame(Command::SUCCESS, $commandTester->execute($options));

        static::assertSame([
            'id' => $options['--id'],
            'name' => $options['--name'],
            'typeId' => $options['--typeId'],
            'languageId' => $options['--languageId'],
            'currencyId' => $options['--currencyId'],
            'paymentMethodId' => $options['--paymentMethodId'],
            'shippingMethodId' => $options['--shippingMethodId'],
            'countryId' => $options['--countryId'],
            'customerGroupId' => $options['--customerGroupId'],
            'navigationCategoryId' => $options['--navigationCategoryId'],
        ], $passedArguments);

        static::assertStringContainsString('Sales channel has been created successfully.', $commandTester->getDisplay());
        static::assertStringContainsString($accessKey, $commandTester->getDisplay());
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('dataProviderTestExecuteFailure')]
    public function testExecuteFailure(array $options): void
    {
        $constraintViolationMock = static::createStub(ConstraintViolationInterface::class);
        $constraintViolationMock->method('getPropertyPath')
            ->willReturn('Dummy');

        $constraintViolationMock->method('getMessage')
            ->willReturn('Dummy Message');

        $constraintViolationListMock = new ConstraintViolationList([$constraintViolationMock]);

        $writeConstraintViolationExceptionMock = static::createStub(WriteConstraintViolationException::class);
        $writeConstraintViolationExceptionMock->method('getViolations')
            ->willReturn($constraintViolationListMock);

        $writeExceptionMock = static::createStub(WriteException::class);
        $writeExceptionMock->method('getExceptions')
            ->willReturn([$writeConstraintViolationExceptionMock]);

        $salesChannelCreatorMock = static::createStub(SalesChannelCreator::class);
        $salesChannelCreatorMock->method('createSalesChannel')
            ->willThrowException($writeExceptionMock);

        $commandTester = new CommandTester(new SalesChannelCreateCommand($salesChannelCreatorMock));

        static::assertSame(Command::SUCCESS, $commandTester->execute($options));

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('Something went wrong.', $display);
        static::assertStringContainsString('Dummy: Dummy Message', $display);
    }

    public static function dataProviderTestExecuteSuccess(): \Generator
    {
        yield 'Test execute success' => [
            'options' => [
                '--id' => Uuid::randomHex(),
                '--typeId' => Uuid::randomHex(),
                '--name' => 'Headless',
                '--languageId' => Uuid::randomHex(),
                '--currencyId' => Uuid::randomHex(),
                '--paymentMethodId' => Uuid::randomHex(),
                '--shippingMethodId' => Uuid::randomHex(),
                '--countryId' => Uuid::randomHex(),
                '--customerGroupId' => Uuid::randomHex(),
                '--navigationCategoryId' => Uuid::randomHex(),
            ],
        ];
    }

    public static function dataProviderTestExecuteFailure(): \Generator
    {
        yield 'Test execute failure' => [
            'options' => [
                '--id' => Uuid::randomHex(),
                '--typeId' => Uuid::randomHex(),
                '--name' => 'Headless',
                '--languageId' => Uuid::randomHex(),
                '--currencyId' => Uuid::randomHex(),
                '--paymentMethodId' => Uuid::randomHex(),
                '--shippingMethodId' => Uuid::randomHex(),
                '--customerGroupId' => Uuid::randomHex(),
                '--navigationCategoryId' => Uuid::randomHex(),
            ],
        ];
    }
}
