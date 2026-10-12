<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\RuleCondition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\RuleCondition\RuleCondition;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RuleCondition::class)]
class RuleConditionTest extends TestCase
{
    public function testToArrayAddsTranslationsForTheDefaultLocale(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');
        $ruleConditions = $manifest->getRuleConditions();
        static::assertNotNull($ruleConditions);

        $result = $ruleConditions->getRuleConditions()[0]->toArray('de-AT');

        static::assertSame(
            ['en-GB' => 'My custom rule condition', 'de-AT' => 'My custom rule condition'],
            $result['name']
        );
        static::assertSame(['en-GB' => 'Operator', 'de-AT' => 'Operator'], $result['config'][0]['config']['label']);
        static::assertSame(
            ['en-GB' => 'Is equal to', 'de-AT' => 'Is equal to'],
            $result['config'][0]['config']['options'][0]['label']
        );
    }
}
