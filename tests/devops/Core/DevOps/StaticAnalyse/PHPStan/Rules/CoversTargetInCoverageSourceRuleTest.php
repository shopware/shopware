<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\CoversTargetInCoverageSourceRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<CoversTargetInCoverageSourceRule>
 */
#[Package('framework')]
class CoversTargetInCoverageSourceRuleTest extends RuleTestCase
{
    private const FIXTURE_DIR = __DIR__ . '/data/CoversTargetInCoverageSourceRule/Tests';

    private const DATA_NAMESPACE = 'Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversTargetInCoverageSourceRule';

    #[TestDox('accepts targets inside the coverage source')]
    public function testAllowedTargets(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/CoversAllowedTargetFixture.php'], []);
    }

    #[TestDox('rejects a target matched by a suffix-scoped directory exclude')]
    public function testSuffixExcludedTarget(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/CoversSuffixExcludedFixture.php'], [
            [
                self::DATA_NAMESPACE . '\project\src\Boilerplate\ExcludedEntity is excluded from the coverage source in phpunit.xml.dist, so it is not a valid coverage target under PHPUnit 12. Cover a class from the coverage source instead, or resolve the exclude.',
                9,
            ],
        ]);
    }

    #[TestDox('rejects a target matched by a whole-directory exclude')]
    public function testDirectoryExcludedTarget(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/CoversDirectoryExcludedFixture.php'], [
            [
                self::DATA_NAMESPACE . '\project\src\Tooling\ToolingHelper is excluded from the coverage source in phpunit.xml.dist, so it is not a valid coverage target under PHPUnit 12. Cover a class from the coverage source instead, or resolve the exclude.',
                9,
            ],
        ]);
    }

    #[TestDox('rejects an excluded CoversTrait target')]
    public function testExcludedTraitTarget(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/CoversExcludedTraitFixture.php'], [
            [
                self::DATA_NAMESPACE . '\project\src\Tooling\ToolingTrait is excluded from the coverage source in phpunit.xml.dist, so it is not a valid coverage target under PHPUnit 12. Cover a class from the coverage source instead, or resolve the exclude.',
                9,
            ],
        ]);
    }

    #[TestDox('rejects a target matched by a file exclude')]
    public function testFileExcludedTarget(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/CoversFileExcludedFixture.php'], [
            [
                self::DATA_NAMESPACE . '\project\src\SingleExcluded is excluded from the coverage source in phpunit.xml.dist, so it is not a valid coverage target under PHPUnit 12. Cover a class from the coverage source instead, or resolve the exclude.',
                9,
            ],
        ]);
    }

    #[TestDox('rejects a target outside of the coverage source include list')]
    public function testOutsideSourceTarget(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/CoversOutsideSourceFixture.php'], [
            [
                self::DATA_NAMESPACE . '\Covered\OutsideSourceClass is not part of the coverage source in phpunit.xml.dist, so it is not a valid coverage target under PHPUnit 12. Cover a class from the coverage source instead.',
                9,
            ],
        ]);
    }

    #[TestDox('skips tests without a class or trait target')]
    public function testCoversNothing(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/CoversNothingFixture.php'], []);
    }

    #[TestDox('skips tests outside of the core unit suite')]
    public function testNonUnitNamespace(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/NonUnitNamespaceFixture.php'], []);
    }

    /**
     * @return CoversTargetInCoverageSourceRule
     */
    protected function getRule(): Rule
    {
        return new CoversTargetInCoverageSourceRule(
            $this->createReflectionProvider(),
            __DIR__ . '/data/CoversTargetInCoverageSourceRule/project',
        );
    }
}
