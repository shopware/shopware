<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\TokenParser;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\FeatureFlagCallTokenParser;
use Shopware\Core\Framework\Feature;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Adapter\Twig\TokenParser\FeatureFlagCallTokenParser
 */
class FeatureFlagCallTokenParserTest extends TestCase
{
    /**
     * @dataProvider providerCode
     */
    public function testCodeRun(string $twigCode, bool $shouldThrow): void
    {
        $_SERVER['TEST_TWIG'] = false;

        $deprecationMessage = null;
        set_error_handler(function ($errno, $errstr) use (&$deprecationMessage) {
            $deprecationMessage = $errstr;

            return true;
        });

        $twig = new Environment(new ArrayLoader(['test.twig' => $twigCode]));
        $twig->addTokenParser(new FeatureFlagCallTokenParser());
        $twig->render('test.twig', [
            'foo' => new TestService(),
        ]);

        restore_error_handler();

        if ($shouldThrow) {
            static::assertNotNull($deprecationMessage);
        } else {
            static::assertNull($deprecationMessage);
        }

        unset($_SERVER['TEST_TWIG']);
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
