<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\PregDollarWithoutDRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<PregDollarWithoutDRule>
 */
#[Package('framework')]
class PregDollarWithoutDRuleTest extends RuleTestCase
{
    public function testReportsNewlineTolerantAnchorsAndUnresolvedPatterns(): void
    {
        $this->analyse([__DIR__ . '/data/PregDollarWithoutDRule/PregUsage.php'], [
            [$this->error('preg_match', '/^[a-z]+$/'), 11],
            [$this->error('preg_match', '/^[a-z]+\\\\$/'), 15],
            [$this->error('preg_match', '/^[a-z]+\Z/'), 17],
            [$this->error('preg_match', '~^[a-z]+$~i'), 19],
            [$this->error('preg_match', '{^[a-z]+$}'), 20],
            [$this->error('preg_match', '/^[a-z]+$ /x'), 21],
            [$this->error('preg_match_all', '#\d+$#'), 22],
            [$this->error('preg_match', '/(a|b$)/'), 23],
            [$this->error('preg_split', '/,$/'), 24],
            [$this->error('preg_grep', '/x$/'), 25],
            [$this->error('preg_filter', '/y$/'), 26],
            [$this->error('preg_match', '/^n$/'), 27],
            [$this->error('preg_match', '/^[0-9a-f]{32}$/'), 32],
            [$this->error('preg_match', '/^foo$/'), 38],
            [$this->error('preg_match', '/^{…}$/i'), 43],
            [$this->error('preg_match', '/^{…}-{…}$/'), 45],
            [$this->error('preg_match', '/^%s$/'), 46],
            [$this->error('preg_match', '/^{…}$/i'), 53],
            [$this->error('preg_match', '/^{…}$/'), 58],
            [$this->error('preg_match', '/^{…}$/{…}'), 61],
            [$this->unresolved('preg_match'), 63],
            [\sprintf(PregDollarWithoutDRule::ERROR, 'preg_match', 'each of the patterns "/a$/", "/b$/", "/c$/"'), 71],
            [$this->error('preg_replace', '/^a$/'), 76],
            [$this->error('preg_replace', '/^{…}$/'), 77],
            [$this->error('preg_replace_callback_array', '/^d$/'), 78],
            [$this->error('preg_match', '/^{…}$/'), 85],
            [$this->error('preg_match', '/^(?:{…})$/'), 89],
            [$this->error('preg_match', '/^{…}$/'), 92],
            [$this->error('preg_match', '/^{…}$/'), 100],
            [$this->error('preg_match', '/^{…}$/'), 102],
            [$this->unresolved('preg_match'), 116],
            [$this->unresolved('preg_match'), 117],
        ]);
    }

    public function testFindsAnAnchorAnywhereInThePattern(): void
    {
        $this->analyse([__DIR__ . '/data/PregDollarWithoutDRule/AnchorPositions.php'], [
            [$this->error('preg_match', '/^([a-z]{2,3})(?:[-_]|$)/i'), 11],
            [$this->error('preg_match', '/^(?:a$|b)c?/'), 12],
            [$this->error('preg_match', '/a\Z|b/'), 13],
            [$this->error('preg_match', '/[$]$/'), 19],
        ]);
    }

    public function testReportsSymfonyRegexConstraints(): void
    {
        $this->analyse([__DIR__ . '/data/PregDollarWithoutDRule/RegexConstraintUsage.php'], [
            [$this->error('Regex', '/^[a-z]+$/'), 14],
            [$this->error('Regex', '/^[a-z]+$/'), 15],
            [$this->error('Regex', '/^\d{2}$/'), 16],
            [$this->error('Regex', '/^[A-Z][a-z]*$/'), 17],
            [$this->unresolved('Regex'), 22],
        ]);
    }

    public function testReportsFinderRegexFilters(): void
    {
        $this->analyse([__DIR__ . '/data/PregDollarWithoutDRule/FinderUsage.php'], [
            [$this->error('Finder::name', '/\.php$/'), 13],
            [$this->error('Finder::notPath', '/\.test\.(js|ts)$/'), 14],
            [$this->error('Finder::path', '#^src/.+$#'), 15],
            [$this->error('Finder::notName', '{\.tmp$}'), 16],
            [$this->error('Finder::contains', '/^foo$/i'), 17],
        ]);
    }

    public function testIgnoresNamespacesThatAreNotEnabled(): void
    {
        $this->analyse([__DIR__ . '/data/PregDollarWithoutDRule/OutsideEnabledNamespace.php'], []);
    }

    protected function getRule(): Rule
    {
        return new PregDollarWithoutDRule(
            new Configuration(['pregDollarWithoutDEnabledNamespaces' => ['Shopware\\Tests\\DevOps\\Core\\DevOps\\StaticAnalyse\\PHPStan\\Rules\\data\\PregDollarWithoutDRule\\']]),
            self::getContainer()->getService('defaultAnalysisParser'),
        );
    }

    private function error(string $function, string $pattern): string
    {
        return \sprintf(PregDollarWithoutDRule::ERROR, $function, 'pattern "' . $pattern . '"');
    }

    private function unresolved(string $function): string
    {
        return \sprintf(PregDollarWithoutDRule::ERROR_UNRESOLVED, $function);
    }
}
