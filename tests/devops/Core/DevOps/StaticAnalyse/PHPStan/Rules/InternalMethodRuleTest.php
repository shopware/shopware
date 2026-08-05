<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Internal\InternalMethodRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<InternalMethodRule>
 */
#[Package('framework')]
class InternalMethodRuleTest extends RuleTestCase
{
    public function testInternalServiceConstructorCannotBeDeprecated(): void
    {
        $fixtureDir = __DIR__ . '/data/InternalMethodRule';

        $this->analyse([$fixtureDir . '/InternalService.php'], [
            [
                'A deprecation annotation must not be used on internal constructors of DI services. Put it on the affected constructor parameter instead.',
                12,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        $fixtureDir = __DIR__ . '/data/InternalMethodRule';

        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory($fixtureDir . '/container.xml');

        /** @phpstan-ignore phpstanApi.method */
        return new InternalMethodRule($factory->create());
    }
}
