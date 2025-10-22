<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\TokenParser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\FeatureFlagCallTokenParser;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(FeatureFlagCallTokenParser::class)]
class FeatureFlagCallTokenParserTest extends TestCase
{
    use EnvTestBehaviour;

    #[IgnoreDeprecations]
    #[DataProvider('providerCode')]
    public function testCodeRun(string $twigCode, bool $shouldThrow): void
    {
        // Deprecation warnings are suppressed in test mode by default
        $this->setEnvVars(['TESTS_RUNNING' => false, 'TEST_TWIG' => false]);

        if ($shouldThrow) {
            $this->expectUserDeprecationMessageMatches('/Since shopware\/core.*Foooo/');
        }

        if (!$shouldThrow) {
            $this->expectNotToPerformAssertions();
        }

        $twig = new Environment(new ArrayLoader(['test.twig' => $twigCode]));
        $twig->addTokenParser(new FeatureFlagCallTokenParser());
        $twig->render('test.twig', [
            'foo' => new TestService(),
        ]);
    }

    /**
     * @return iterable<array{0: string, 1: bool}>
     */
    public static function providerCode(): iterable
    {
        yield 'silenced' => [
            '{% sw_silent_feature_call "TEST_TWIG" %}{% do foo.call %}{% endsw_silent_feature_call %}',
            false,
        ];

        yield 'triggers deprecation' => [
            '{% do foo.call %}',
            true,
        ];

        yield 'test injection' => [
            '{% sw_silent_feature_call "aaa\' . system(\'id\') . \'bbb" %}{% do foo.call %}{% endsw_silent_feature_call %}',
            true,
        ];
    }
}

/**
 * @internal
 */
class TestService
{
    public function call(): void
    {
        Feature::triggerDeprecationOrThrow('TEST_TWIG', 'Foooo');
    }
}
