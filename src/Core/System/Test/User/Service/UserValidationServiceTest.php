<?php declare(strict_types=1);

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class UserValidationServiceTest extends \PHPUnit\Framework\TestCase
{
    use IntegrationTestBehaviour;

    private $userRepository;
    private $localeRepository;
    private $userValidationService;

    public function setUp(): void
    {
        $this->userRepository = $this->getContainer()->get('user.repository');
        $this->localeRepository = $this->getContainer()->get('locale.repository');
        $this->userValidationService = $this->getContainer()->get(\Shopware\Core\System\User\Service\UserValidationService::class);
    }

    public function testIfReturnsTrueForUniqueEmails(): void
    {
        $userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getData();
        $firstLocale = array_pop($localeIds)['primary_key'];

        $this->userRepository->create([
            [
                'id' => $userId,
                'username' => 'some User',
                'firstName' => 'first',
                'lastName' => 'last',
                'localeId' => $firstLocale,
                'email' => 'user@shopware.com',
                'password' => '1234',
            ],
        ], $context);

        $userIdToTest = Uuid::randomHex();
        static::assertTrue($this->userValidationService->checkEmailUnique('some@other.email', $userIdToTest, $context));
        static::assertTrue($this->userValidationService->checkEmailUnique('user@shopware.com', $userId, $context));
    }

    public function testIfReturnsFalseForDuplicateEmails(): void
    {
        $userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getData();
        $firstLocale = array_pop($localeIds)['primary_key'];

        $this->userRepository->create([
            [
                'id' => $userId,
                'username' => 'some User',
                'firstName' => 'first',
                'lastName' => 'last',
                'localeId' => $firstLocale,
                'email' => 'user@shopware.com',
                'password' => '1234',
            ],
        ], $context);

        $userIdToTest = Uuid::randomHex();
        static::assertFalse($this->userValidationService->checkEmailUnique('user@shopware.com', $userIdToTest, $context));
    }

    public function testIfReturnsTrueForUniqueUsernames(): void
    {
        $userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getData();
        $firstLocale = array_pop($localeIds)['primary_key'];

        $this->userRepository->create([
            [
                'id' => $userId,
                'username' => 'some User',
                'firstName' => 'first',
                'lastName' => 'last',
                'localeId' => $firstLocale,
                'email' => 'user@shopware.com',
                'password' => '1234',
            ],
        ], $context);

        $userIdToTest = Uuid::randomHex();
        static::assertTrue($this->userValidationService->checkUsernameUnique('other User', $userIdToTest, $context));
        static::assertTrue($this->userValidationService->checkUsernameUnique('some User', $userId, $context));
    }

    public function testIfReturnsFalseForDuplicateUsernames(): void
    {
        $userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getData();
        $firstLocale = array_pop($localeIds)['primary_key'];

        $this->userRepository->create([
            [
                'id' => $userId,
                'username' => 'some User',
                'firstName' => 'first',
                'lastName' => 'last',
                'localeId' => $firstLocale,
                'email' => 'user@shopware.com',
                'password' => '1234',
            ],
        ], $context);

        $userIdToTest = Uuid::randomHex();
        static::assertFalse($this->userValidationService->checkUsernameUnique('some User', $userIdToTest, $context));
    }
}
