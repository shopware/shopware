<?php
declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PhpParser\Node;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoSharedTwigRenderWithoutRequestRule;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig\RequestStackPushCollector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig\SalesChannelContextAttributeCollector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig\TwigEnvironmentOriginCollector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig\TwigRenderCollector;
use Shopware\Core\Framework\Log\Package;

// the fixture namespaces are what the rule is gated on, so they are not autoloadable from tests/devops
require_once __DIR__ . '/../data/NoSharedTwigRenderWithoutRequest/integration-tests.php';
require_once __DIR__ . '/../data/NoSharedTwigRenderWithoutRequest/unit-test.php';

/**
 * @internal
 *
 * @extends RuleTestCase<NoSharedTwigRenderWithoutRequestRule>
 */
#[Package('framework')]
class NoSharedTwigRenderWithoutRequestRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([
            __DIR__ . '/../data/NoSharedTwigRenderWithoutRequest/integration-tests.php',
            __DIR__ . '/../data/NoSharedTwigRenderWithoutRequest/unit-test.php',
        ], [
            // the container's `twig`, called on the lookup itself
            [self::message('Twig\\Environment::render()'), 33],
            // ... through a local variable
            [self::message('Twig\\Environment::render()'), 42],
            // ... a template handed out by a property set up in setUp(), rendered right away
            [self::message('Twig\\TemplateWrapper::render()'), 57],
            // ... a template kept in a variable and rendered later
            [self::message('Twig\\TemplateWrapper::render()'), 78],
            // ... a block of a template loaded through a property
            [self::message('Twig\\TemplateWrapper::renderBlock()'), 94],
            // ... through `$this->twig = $twig;`, looked up by its class name
            [self::message('Twig\\Environment::display()'), 110],
            // ... through a property an abstract parent sets up
            [self::message('Twig\\Environment::render()'), 128],
            // a bare pushed request carries no sales channel context
            [self::message('Twig\\Environment::render()'), 141],
            // a context request built in one test does not cover the bare request another test renders in
            [self::message('Twig\\Environment::render()'), 193],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoSharedTwigRenderWithoutRequestRule();
    }

    /**
     * @return list<Collector<Node, mixed>>
     */
    protected function getCollectors(): array
    {
        return [
            new TwigRenderCollector(new Configuration(['sharedTwigRenderEnabledNamespaces' => ['Shopware\\Tests\\Integration\\']])),
            new TwigEnvironmentOriginCollector(),
            new RequestStackPushCollector(),
            new SalesChannelContextAttributeCollector(),
        ];
    }

    private static function message(string $call): string
    {
        return \sprintf(NoSharedTwigRenderWithoutRequestRule::ERROR, $call);
    }
}
