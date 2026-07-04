<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Mfa;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaPolicyService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(MfaPolicyService::class)]
class MfaPolicyServiceTest extends TestCase
{
    public function testNoEnrolledFactorsMeansNoChallenge(): void
    {
        $service = $this->createService(enrolledTypes: []);

        $userId = Uuid::randomHex();

        static::assertFalse($service->requiresSecondFactor($userId));
        static::assertSame([], $service->availableFactors($userId));
    }

    public function testEnrolledFactorTriggersChallengeEvenWithPolicyNone(): void
    {
        $service = $this->createService(enrolledTypes: ['totp'], required: MfaPolicyService::REQUIRED_NONE);

        $userId = Uuid::randomHex();

        static::assertTrue($service->requiresSecondFactor($userId));
        static::assertSame(['totp'], $service->availableFactors($userId));
    }

    public function testFallbackOnlyEnrollmentDoesNotTriggerChallenge(): void
    {
        // Recovery codes alone are not a real second factor, only a fallback.
        $service = $this->createService(enrolledTypes: ['recovery_codes']);

        $userId = Uuid::randomHex();

        static::assertFalse($service->requiresSecondFactor($userId));
        static::assertSame([], $service->availableFactors($userId));
    }

    public function testFallbackIsOfferedAlongsideRealFactor(): void
    {
        $service = $this->createService(enrolledTypes: ['totp', 'recovery_codes']);

        static::assertSame(['totp', 'recovery_codes'], $service->availableFactors(Uuid::randomHex()));
    }

    public function testDisabledMethodIsNotAUsableFactor(): void
    {
        $methodSettings = new MethodSettingsService(new StaticSystemConfigService([
            'core.adminAuth.methods' => ['totp' => ['enabled' => false, 'isSecondFactor' => true, 'priority' => 80]],
        ]));

        $service = $this->createService(enrolledTypes: ['totp'], methodSettings: $methodSettings);

        static::assertFalse($service->requiresSecondFactor(Uuid::randomHex()));
    }

    public function testEnrollmentRequiredForAll(): void
    {
        $service = $this->createService(enrolledTypes: [], required: MfaPolicyService::REQUIRED_ALL);

        static::assertTrue($service->isEnrollmentRequired(Uuid::randomHex()));
    }

    public function testEnrollmentRequiredForAdminsChecksAdminFlag(): void
    {
        $adminService = $this->createService(enrolledTypes: [], required: MfaPolicyService::REQUIRED_ADMINS, isAdmin: true);
        $regularService = $this->createService(enrolledTypes: [], required: MfaPolicyService::REQUIRED_ADMINS, isAdmin: false);

        static::assertTrue($adminService->isEnrollmentRequired(Uuid::randomHex()));
        static::assertFalse($regularService->isEnrollmentRequired(Uuid::randomHex()));
    }

    public function testEnrollmentNotRequiredWithPolicyNone(): void
    {
        $service = $this->createService(enrolledTypes: [], required: MfaPolicyService::REQUIRED_NONE, isAdmin: true);

        static::assertFalse($service->isEnrollmentRequired(Uuid::randomHex()));
    }

    public function testRequiredButNotEnrolledUserGetsNoChallenge(): void
    {
        // Enforced enrollment is a later phase: a required user without factors logs in without MFA.
        $service = $this->createService(enrolledTypes: [], required: MfaPolicyService::REQUIRED_ALL);

        $userId = Uuid::randomHex();

        static::assertTrue($service->isEnrollmentRequired($userId));
        static::assertSame([], $service->availableFactors($userId));
    }

    public function testChallengeTtlDefaultsTo300Seconds(): void
    {
        $service = $this->createService(enrolledTypes: []);

        static::assertSame(MfaPolicyService::DEFAULT_CHALLENGE_TTL, $service->challengeTtl());
        static::assertSame(300, $service->challengeTtl());
    }

    public function testChallengeTtlIsConfigurableViaSystemConfig(): void
    {
        $service = $this->createService(
            enrolledTypes: [],
            systemConfig: new StaticSystemConfigService(['core.adminAuth.mfaChallengeTtlSeconds' => 120]),
        );

        static::assertSame(120, $service->challengeTtl());
    }

    public function testNonPositiveChallengeTtlFallsBackToDefault(): void
    {
        $service = $this->createService(
            enrolledTypes: [],
            systemConfig: new StaticSystemConfigService(['core.adminAuth.mfaChallengeTtlSeconds' => -1]),
        );

        static::assertSame(300, $service->challengeTtl());
    }

    /**
     * @param list<string> $enrolledTypes
     * @param 'none'|'admins'|'all' $required
     */
    private function createService(
        array $enrolledTypes,
        string $required = MfaPolicyService::REQUIRED_NONE,
        bool $isAdmin = false,
        ?MethodSettingsService $methodSettings = null,
        ?StaticSystemConfigService $systemConfig = null,
    ): MfaPolicyService {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn($enrolledTypes);
        $connection->method('fetchOne')->willReturn($isAdmin ? '1' : '0');

        $systemConfig ??= new StaticSystemConfigService();

        return new MfaPolicyService(
            $connection,
            $systemConfig,
            $methodSettings ?? new MethodSettingsService($systemConfig),
            $required,
        );
    }
}
