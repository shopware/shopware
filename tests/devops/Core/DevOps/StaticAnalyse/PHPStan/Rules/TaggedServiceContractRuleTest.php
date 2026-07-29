<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\TaggedServiceContractRule;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule\Contract;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule\WrongContract;

/**
 * @internal
 *
 * @extends RuleTestCase<TaggedServiceContractRule>
 */
class TaggedServiceContractRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $fixtureDir = __DIR__ . '/data/TaggedServiceContractRule';

        $this->analyse([
            $fixtureDir . '/BadTaggedService.php',
            $fixtureDir . '/Contract.php',
            $fixtureDir . '/GoodTaggedService.php',
            $fixtureDir . '/InternalContract.php',
            $fixtureDir . '/InternalContractConsumer.php',
            $fixtureDir . '/LocatorConsumer.php',
            $fixtureDir . '/MappedConsumer.php',
            $fixtureDir . '/UnmappedConsumer.php',
            $fixtureDir . '/UnmappedContract.php',
            $fixtureDir . '/UnionMappedConsumer.php',
            $fixtureDir . '/WrongContract.php',
            $fixtureDir . '/WrongMappedConsumer.php',
        ], [
            [
                'Service "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule\BadTaggedService" is tagged with "test.mapped" but its class "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule\BadTaggedService" does not implement or extend the configured tag contract "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule\Contract".',
                5,
            ],
            [
                'Tagged service tag "test.unmapped" is consumed as "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule\UnmappedContract", but the tag has no declared contract in TaggedServiceContractRule. Add the tag contract to the rule configuration or mark "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule\UnmappedContract" as @internal.',
                5,
            ],
            [
                'Service "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule\WrongMappedConsumer" injects services tagged with "test.mapped" into parameter $services, but the parameter is not typed as the configured tag contract "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule\Contract".',
                5,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        $fixtureDir = __DIR__ . '/data/TaggedServiceContractRule';

        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory($fixtureDir . '/container.xml');

        /** @phpstan-ignore phpstanApi.method */
        return new TaggedServiceContractRule($factory->create(), self::createReflectionProvider(), [
            'test.mapped' => Contract::class,
            'test.union' => [Contract::class, WrongContract::class],
        ], [], $fixtureDir . '/container.xml');
    }
}
