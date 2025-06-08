<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\SalesChannel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelCreateCommand;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(SalesChannelCreateCommand::class)]
class SalesChannelCreateCommandTest extends TestCase
{
    /**
     * @param array<string, mixed> $inputMockValues
     */
    #[DataProvider('dataProviderTestExecuteSuccess')]
    public function testExecuteSuccess(array $inputMockValues): void
    {
        $accessKey = AccessKeyHelper::generateAccessKey('sales-channel');

        $salesChannelCreatorMock = $this->createMock(SalesChannelCreator::class);
        $salesChannelCreatorMock->method('createSalesChannel')
            ->willReturn($accessKey);

        $salesChannelCreateCmd = new SalesChannelCreateCommand($salesChannelCreatorMock);

        $refMethod = ReflectionHelper::getMethod(SalesChannelCreateCommand::class, 'execute');

        $inputMock = $this->createMock(InputInterface::class);
        $inputMock->method('getOption')
            ->willReturnOnConsecutiveCalls(...array_values($inputMockValues));

        $outputMock = $this->createMock(OutputInterface::class);

        $result = $refMethod->invoke($salesChannelCreateCmd, $inputMock, $outputMock);

        static::assertSame(Command::SUCCESS, $result);
    }

    /**
     * @param array<string, mixed> $inputMockValues
     */
    #[DataProvider('dataProviderTestExecuteFailure')]
    public function testExecuteFailure(array $inputMockValues): void
    {
        $constraintViolationMock = $this->createMock(ConstraintViolationInterface::class);
        $constraintViolationMock->method('getPropertyPath')
            ->willReturn('Dummy');

        $constraintViolationMock->method('getMessage')
            ->willReturn('Dummy Message');

        $constraintViolationListMock = new ConstraintViolationList([$constraintViolationMock]);

        $writeConstraintViolationExceptionMock = $this->createMock(WriteConstraintViolationException::class);
        $writeConstraintViolationExceptionMock->method('getViolations')
            ->willReturn($constraintViolationListMock);

        $writeExceptionMock = $this->createMock(WriteException::class);
        $writeExceptionMock->method('getExceptions')
            ->willReturn([$writeConstraintViolationExceptionMock]);

        $salesChannelCreatorMock = $this->createMock(SalesChannelCreator::class);
        $salesChannelCreatorMock->method('createSalesChannel')
            ->willThrowException($writeExceptionMock);

        $salesChannelCreateCmd = new SalesChannelCreateCommand($salesChannelCreatorMock);

        $refMethod = ReflectionHelper::getMethod(SalesChannelCreateCommand::class, 'execute');

        $inputMock = $this->createMock(InputInterface::class);
        $inputMock->method('getOption')
            ->willReturnOnConsecutiveCalls(...array_values($inputMockValues));

        $outputMock = $this->createMock(OutputInterface::class);

        $result = $refMethod->invoke($salesChannelCreateCmd, $inputMock, $outputMock);

        static::assertSame(Command::SUCCESS, $result);
    }

    public static function dataProviderTestExecuteSuccess(): \Generator
    {
        yield 'Test execute success' => [
            'inputMockValues' => [
                'id' => Uuid::randomHex(),
                'typeId' => Uuid::randomHex(),
                'name' => 'Headless',
                'languageId' => Uuid::randomHex(),
                'currencyId' => Uuid::randomHex(),
                'snippetSetId' => Uuid::randomHex(),
                'paymentMethodId' => Uuid::randomHex(),
                'shippingMethodId' => Uuid::randomHex(),
                'customerGroupId' => Uuid::randomHex(),
                'navigationCategoryId' => Uuid::randomHex(),
            ],
        ];
    }

    public static function dataProviderTestExecuteFailure(): \Generator
    {
        yield 'Test execute failure' => [
            'inputMockValues' => [
                'id' => Uuid::randomHex(),
                'typeId' => Uuid::randomHex(),
                'name' => 'Headless',
                'languageId' => Uuid::randomHex(),
                'currencyId' => Uuid::randomHex(),
                'snippetSetId' => Uuid::randomHex(),
                'paymentMethodId' => Uuid::randomHex(),
                'shippingMethodId' => Uuid::randomHex(),
                'customerGroupId' => Uuid::randomHex(),
                'navigationCategoryId' => Uuid::randomHex(),
            ],
        ];
    }
}
