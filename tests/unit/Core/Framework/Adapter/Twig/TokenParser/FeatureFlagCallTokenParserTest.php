<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\TokenParser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestDox;
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

    #[TestDox('sw_silent_feature_call wrapping an inactive flag suppresses Feature::triggerDeprecationOrThrow inside the rendered closure')]
    public function testSilentFeatureCallSuppressesDeprecation(): void
    {
        // Deprecation warnings are suppressed in test mode by default
        $this->setEnvVars(['TESTS_RUNNING' => false, 'TEST_TWIG' => false]);

        $this->expectNotToPerformAssertions();

        $twig = new Environment(new ArrayLoader([
            'test.twig' => '{% sw_silent_feature_call "TEST_TWIG" %}{% do foo.call %}{% endsw_silent_feature_call %}',
        ]));
        $twig->addTokenParser(new FeatureFlagCallTokenParser());
        $twig->render('test.twig', [
            'foo' => new TestService(),
        ]);
    }

    #[IgnoreDeprecations]
    #[DataProvider('providerCode')]
    public function testCodeRun(string $twigCode): void
    {
        // Deprecation warnings are suppressed in test mode by default
        $this->setEnvVars(['TESTS_RUNNING' => false, 'TEST_TWIG' => false]);

        $this->expectUserDeprecationMessage('Foooo');

        $twig = new Environment(new ArrayLoader(['test.twig' => $twigCode]));
        $twig->addTokenParser(new FeatureFlagCallTokenParser());
        $twig->render('test.twig', [
            'foo' => new TestService(),
        ]);
    }

    /**
     * @return iterable<array{0: string}>
     */
    public static function providerCode(): iterable
    {
        yield 'triggers deprecation' => [
            '{% do foo.call %}',
        ];

        yield 'test injection' => [
            '{% sw_silent_feature_call "aaa\' . system(\'id\') . \'bbb" %}{% do foo.call %}{% endsw_silent_feature_call %}',
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
