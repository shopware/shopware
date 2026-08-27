<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestPackageMatchRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<TestPackageMatchRule>
 */
#[Package('framework')]
class TestPackageMatchRuleTest extends RuleTestCase
{
    private const FIXTURE_DIR = __DIR__ . '/data/TestPackageMatchRule/Tests';

    #[TestDox('accepts a unit test whose package matches the covered class')]
    public function testMatchingPackage(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/MatchingPackageFixture.php'], []);
    }

    #[TestDox('rejects a unit test whose package differs from the covered class')]
    public function testMismatchedPackage(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/MismatchedPackageFixture.php'], [
            [
                'The #[Package(\'framework\')] attribute of this test does not match the covered Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule\Covered\CheckoutService (checkout)',
                10,
            ],
        ]);
    }

    #[TestDox('treats fundamentals@<area> on the covered class as equal to <area>')]
    public function testFundamentalsOnCoveredClass(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/FundamentalsEquivalenceFixture.php'], []);
    }

    #[TestDox('treats fundamentals@<area> on the test as equal to <area>')]
    public function testFundamentalsOnTest(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/FundamentalsOnTestFixture.php'], []);
    }

    #[TestDox('accepts a unit test matching at least one of several covered classes')]
    public function testOneOfManyMatches(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/OneOfManyMatchesFixture.php'], []);
    }

    #[TestDox('skips tests without a package attribute')]
    public function testMissingTestPackage(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/MissingTestPackageFixture.php'], []);
    }

    #[TestDox('skips unit tests covering only unpackaged code')]
    public function testUnpackagedCoveredClass(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/UnpackagedCoveredFixture.php'], []);
    }

    #[TestDox('compares migration tests against their covered class')]
    public function testMigrationSuiteEnforced(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/MigrationSuiteFixture.php'], [
            [
                'The #[Package(\'framework\')] attribute of this test does not match the covered Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule\Covered\CheckoutService (checkout)',
                10,
            ],
        ]);
    }

    #[TestDox('skips tests of downstream repositories, which carry their own package taxonomy')]
    public function testCommercialSuiteNotEnforced(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/CommercialSuiteFixture.php'], []);
    }

    #[TestDox('rejects a unit test whose package differs from the covered trait')]
    public function testMismatchedTrait(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/MismatchedTraitFixture.php'], [
            [
                'The #[Package(\'framework\')] attribute of this test does not match the covered Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule\Covered\CheckoutHelperTrait (checkout)',
                10,
            ],
        ]);
    }

    #[TestDox('accepts an integration test whose package appears in the mirrored src directory')]
    public function testIntegrationMatching(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/IntegrationMatchingFixture.php'], []);
    }

    #[TestDox('rejects an integration test whose package appears nowhere in the mirrored src directory')]
    public function testIntegrationMismatched(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/IntegrationMismatchedFixture.php'], [
            [
                'The #[Package(\'discovery\')] attribute of this test does not match the mirrored src/Core/Checkout/Cart (checkout)',
                8,
            ],
        ]);
    }

    #[TestDox('resolves a deeper integration test namespace to the nearest existing src directory')]
    public function testIntegrationWalkUp(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/IntegrationWalkUpFixture.php'], [
            [
                'The #[Package(\'discovery\')] attribute of this test does not match the mirrored src/Core/Checkout/Cart (checkout)',
                8,
            ],
        ]);
    }

    #[TestDox('treats fundamentals@<area> in the mirrored src directory as equal to <area>')]
    public function testIntegrationFundamentals(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/IntegrationFundamentalsFixture.php'], []);
    }

    #[TestDox('skips integration tests whose namespace mirrors no src directory')]
    public function testIntegrationUnmirrored(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/IntegrationUnmirroredFixture.php'], []);
    }

    /**
     * @return TestPackageMatchRule
     */
    protected function getRule(): Rule
    {
        return new TestPackageMatchRule(
            $this->createReflectionProvider(),
            __DIR__ . '/data/TestPackageMatchRule/project',
        );
    }
}
