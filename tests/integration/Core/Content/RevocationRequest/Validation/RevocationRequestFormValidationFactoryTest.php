<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\RevocationRequest\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
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
            'expectedViolationCount' => 4,
        ];

        yield 'all is invalid but names are not required' => [
            'formData' => [],
            'requireNames' => false,
            'expectedViolationCount' => 2,
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
        $formData['firstName'] = self::getToLongFirstName();
        yield 'firstName is longer than maximum length' => [
            'formData' => $formData,
            'requireNames' => true,
            'expectedViolationCount' => 1,
        ];

        $formData = self::createValidData();
        $formData['firstName'] = self::getToLongFirstName();
        yield 'firstName is longer than maximum length but not required' => [
            'formData' => $formData,
            'requireNames' => false,
            'expectedViolationCount' => 1,
        ];

        $formData = self::createValidData();
        $formData['lastName'] = self::getToLongLastName();
        yield 'lastName is longer than maximum length but not required' => [
            'formData' => $formData,
            'requireNames' => false,
            'expectedViolationCount' => 1,
        ];

        $formData = self::createValidData();
        $formData['lastName'] = self::getToLongLastName();
        yield 'lastName is longer than maximum length' => [
            'formData' => $formData,
            'requireNames' => true,
            'expectedViolationCount' => 1,
        ];

        $formData = self::createValidData();
        unset($formData['firstName'], $formData['lastName']);
        yield 'firstName and lastName is missing' => [
            'formData' => $formData,
            'requireNames' => true,
            'expectedViolationCount' => 2,
        ];

        $formData = self::createValidData();
        unset($formData['firstName'], $formData['lastName']);
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
        $formData['contractNumber'] = self::getToLongComment();
        yield 'contractNumber is longer than maximum length' => [
            'formData' => $formData,
            'requireNames' => true,
            'expectedViolationCount' => 1,
        ];

        $formData = self::createValidData();
        unset($formData['comment']);
        yield 'comment is missing' => [
            'formData' => $formData,
            'requireNames' => true,
            'expectedViolationCount' => 0,
        ];

        $formData = self::createValidData();
        $formData['comment'] = '';
        yield 'comment is empty' => [
            'formData' => $formData,
            'requireNames' => true,
            'expectedViolationCount' => 0,
        ];

        $formData = self::createValidData();
        $formData['comment'] = self::getToLongComment();
        yield 'comment is longer than maximum length' => [
            'formData' => $formData,
            'requireNames' => true,
            'expectedViolationCount' => 1,
        ];

        $formData = self::createValidData();
        $formData['email'] = self::getToLongEmail();
        yield 'email is longer than maximum length' => [
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

    private static function getToLongComment(): string
    {
        $comment = <<<EOT
Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.
        
Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.

Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi.

Nam liber tempor cum soluta nobis eleifend option congue nihil imperdiet doming id quod mazim placerat facer possim assum. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat.

Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis. 

At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, At accusam aliquyam diam diam dolore dolores duo eirmod eos erat, et nonumy sed tempor et et invidunt justo labore Stet clita ea et gubergren, kasd magna no rebum. sanctus sea sed takimata ut vero voluptua. est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat.

Consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus.Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est
EOT;

        static::assertGreaterThan(RevocationRequestFormValidationFactory::COMMENT_MAX_LENGTH, \strlen($comment));

        return $comment;
    }

    private static function getToLongFirstName(): string
    {
        $firstName = self::get300SingsString();

        static::assertGreaterThan(CustomerDefinition::MAX_LENGTH_FIRST_NAME, \strlen($firstName));

        return $firstName;
    }

    private static function getToLongLastName(): string
    {
        $firstName = self::get300SingsString();

        static::assertGreaterThan(CustomerDefinition::MAX_LENGTH_FIRST_NAME, \strlen($firstName));

        return $firstName;
    }

    private static function get300SingsString(): string
    {
        return 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lore';
    }

    private static function getToLongEmail(): string
    {
        $email = '0F1Rc7qNnRxHtmp3SLqCQBMjbxJ7cmhX18nfQLYZXgQwT5Kxx51Z3zMnvPY40g8vMvpScequSWYyGzXGy5vTxVtHjAn2VBGtEtQpQEMFxPbyyc9rbyJVAhYeMNUW4iH7uUW2njQLurLRCyHfG63LyEuBtSMWnegfkXuity56VubT2KRVJtiCJAhUZL3jvJkamvuSpBf4M1v5HMB64VgVBqRyQtX2juc76fn9A7Ymthvketr5UNmcf5GLac7XHVbbyghJYf4tTh6LFrqLRmUNxXVkqfAmPdtbVWGXnB4Hqghn@Hf06fargukmjnQaSC0YwyuQnDi09UEErU1A246ZGtvWCdNCB0g.test';

        static::assertGreaterThan(RevocationRequestFormValidationFactory::EMAIL_MAX_LENGTH, \strlen($email));

        return $email;
    }
}
