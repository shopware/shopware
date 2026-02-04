<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\RevocationRequest\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\RevocationRequest\Validation\RevocationRequestFormValidationFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(RevocationRequestFormValidationFactory::class)]
class RevocationRequestFormValidationFactoryTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @param array<string, string> $formData
     */
    #[DataProvider('createTestData')]
    public function testCreate(array $formData, bool $requireNames, int $expectedViolationCount): void
    {
        $factory = new RevocationRequestFormValidationFactory(
            $this->createEventDispatcherMock(),
            $this->createSystemConfigServiceMock($requireNames)
        );

        $validation = $factory->create($this->createSalesChannelContextMock());

        $validator = static::getContainer()->get(DataValidator::class);

        static::assertCount($expectedViolationCount, $validator->getViolations($formData, $validation));
    }

    public static function createTestData(): \Generator
    {
        yield 'all is valid' => [
            'formData' => self::createValidData(),
            'requireNames' => true,
            'expectedViolationCount' => 0,
        ];

        yield 'all is invalid' => [
            'formData' => [],
            'requireNames' => true,
            'expectedViolationCount' => 5,
        ];

        yield 'all is invalid but names are not required' => [
            'formData' => [],
            'requireNames' => false,
            'expectedViolationCount' => 3,
        ];

        $formData = self::createValidData();
        unset($formData['firstName']);
        yield 'firstName is missing' => [
            'formData' => $formData,
            'requireNames' => true,
            'expectedViolationCount' => 1,
        ];

        $formData = self::createValidData();
        unset($formData['firstName']);
        yield 'firstName is missing but not required' => [
            'formData' => $formData,
            'requireNames' => false,
            'expectedViolationCount' => 0,
        ];

        $formData = self::createValidData();
        unset($formData['firstName']);
        unset($formData['lastName']);
        yield 'firstName and lastName is missing' => [
            'formData' => $formData,
            'requireNames' => true,
            'expectedViolationCount' => 2,
        ];

        $formData = self::createValidData();
        unset($formData['firstName']);
        unset($formData['lastName']);
        yield 'firstName and lastName is missing but not required' => [
            'formData' => $formData,
            'requireNames' => false,
            'expectedViolationCount' => 0,
        ];

        $formData = self::createValidData();
        unset($formData['email']);
        yield 'email is missing' => [
            'formData' => $formData,
            'requireNames' => true,
            'expectedViolationCount' => 1,
        ];

        $formData = self::createValidData();
        unset($formData['contractNumber']);
        yield 'contractNumber is missing' => [
            'formData' => $formData,
            'requireNames' => true,
            'expectedViolationCount' => 1,
        ];

        $formData = self::createValidData();
        unset($formData['comment']);
        yield 'comment is missing' => [
            'formData' => $formData,
            'requireNames' => true,
            'expectedViolationCount' => 1,
        ];
    }

    private function createSalesChannelContextMock(): SalesChannelContext&MockObject
    {
        return $this->createMock(SalesChannelContext::class);
    }

    private function createSystemConfigServiceMock(?bool $returns = true): SystemConfigService&MockObject
    {
        $mock = $this->createMock(SystemConfigService::class);
        $mock->expects($this->exactly(2))->method('get')
            ->willReturn($returns);

        return $mock;
    }

    private function createEventDispatcherMock(): EventDispatcherInterface&MockObject
    {
        $mock = $this->createMock(EventDispatcherInterface::class);
        $mock->expects($this->once())->method('dispatch');

        return $mock;
    }

    /**
     * @return array<string, string>
     */
    private static function createValidData(): array
    {
        return [
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'max@muster.com',
            'contractNumber' => 'SW123456789',
            'comment' => 'This is a simple comment',
        ];
    }
}
