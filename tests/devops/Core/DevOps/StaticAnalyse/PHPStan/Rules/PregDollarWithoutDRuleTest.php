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
            [$this->error('preg_match_all', '#\d+$#'), 21],
            [$this->error('preg_split', '/,$/'), 23],
            [$this->error('preg_grep', '/x$/'), 24],
            [$this->error('preg_filter', '/y$/'), 25],
            [$this->error('preg_match', '/^[0-9a-f]{32}$/'), 30],
            [$this->error('preg_match', '/^foo$/'), 36],
            [$this->error('preg_match', '/^{…}$/i'), 41],
            [$this->error('preg_match', '/^{…}-{…}$/'), 43],
            [$this->error('preg_match', '/^{…}%$/'), 44],
            [$this->error('preg_match', '/^{…}$/i'), 51],
            [$this->error('preg_match', '/^{…}$/'), 56],
            [$this->error('preg_match', '/^{…}$/{…}'), 59],
            [$this->error('preg_replace', '/^a$/'), 65],
            [$this->error('preg_replace_callback_array', '/^c$/'), 66],
            [\sprintf(PregDollarWithoutDRule::ERROR_UNRESOLVED, 'preg_match'), 71],
            [\sprintf(PregDollarWithoutDRule::ERROR_UNRESOLVED, 'preg_match'), 72],
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
        return \sprintf(PregDollarWithoutDRule::ERROR, $function, $pattern);
    }
}
