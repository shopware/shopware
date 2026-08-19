<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\CoversPackageMatchRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<CoversPackageMatchRule>
 */
#[Package('framework')]
class CoversPackageMatchRuleTest extends RuleTestCase
{
    private const FIXTURE_DIR = __DIR__ . '/data/CoversPackageMatchRule/Tests';

    #[TestDox('accepts a test whose package matches the covered class')]
    public function testMatchingPackage(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/MatchingPackageFixture.php'], []);
    }

    #[TestDox('rejects a test whose package differs from the covered class')]
    public function testMismatchedPackage(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/MismatchedPackageFixture.php'], [
            [
                'The #[Package(\'framework\')] attribute of this test does not match the covered Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversPackageMatchRule\Covered\CheckoutService (checkout)',
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

    #[TestDox('accepts a test matching at least one of several covered classes')]
    public function testOneOfManyMatches(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/OneOfManyMatchesFixture.php'], []);
    }

    #[TestDox('skips tests without a package attribute')]
    public function testMissingTestPackage(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/MissingTestPackageFixture.php'], []);
    }

    #[TestDox('skips tests covering only unpackaged code')]
    public function testUnpackagedCoveredClass(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/UnpackagedCoveredFixture.php'], []);
    }

    #[TestDox('rejects a test whose package differs from the covered trait')]
    public function testMismatchedTrait(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/MismatchedTraitFixture.php'], [
            [
                'The #[Package(\'framework\')] attribute of this test does not match the covered Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversPackageMatchRule\Covered\CheckoutHelperTrait (checkout)',
                10,
            ],
        ]);
    }

    /**
     * @return CoversPackageMatchRule
     */
    protected function getRule(): Rule
    {
        return new CoversPackageMatchRule(
            new Configuration([
                'coversPackageMatchNamespaces' => [
                    'Shopware\\Tests\\DevOps\\Core\\DevOps\\StaticAnalyse\\PHPStan\\Rules\\data\\CoversPackageMatchRule\\Tests\\',
                ],
            ]),
            $this->createReflectionProvider(),
        );
    }
}
