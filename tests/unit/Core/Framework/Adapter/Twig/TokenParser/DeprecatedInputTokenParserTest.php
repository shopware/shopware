<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\TokenParser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\DeprecatedInputExtension;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\DeprecatedInputTokenParser;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(DeprecatedInputTokenParser::class)]
class DeprecatedInputTokenParserTest extends TestCase
{
    /**
     * @var array<string, FeatureFlagConfig>
     */
    private array $featureConfigBackup;

    protected function setUp(): void
    {
        $this->featureConfigBackup = Feature::getRegisteredFeatures();

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'v6.8.0.0' => ['major' => true],
            'EXPERIMENTAL_FLAG' => ['major' => false],
        ]);
    }

    protected function tearDown(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->featureConfigBackup);
    }

    public function testAcceptsRootAndDottedInputPaths(): void
    {
        $twig = $this->createTwig(<<<'TWIG'
{% sw_deprecated input 'type' replaced_by='addressType' removed_in='v6.8.0.0' %}
{% sw_deprecated input 'child.snippet_name' message='Use the translated name.' removed_in='v6.8.0.0' %}
TWIG);

        static::assertSame('', $twig->render('index.html.twig'));
    }

    #[DataProvider('invalidDeclarationProvider')]
    public function testRejectsInvalidDeclarations(string $declaration, string $expectedMessage): void
    {
        $twig = $this->createTwig($declaration);

        try {
            $twig->load('index.html.twig');
            static::fail('The invalid declaration should not compile.');
        } catch (SyntaxError $exception) {
            static::assertStringContainsString($expectedMessage, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDeclarationProvider(): iterable
    {
        yield 'only input declarations are supported' => [
            '{% sw_deprecated template replaced_by=\'new.html.twig\' removed_in=\'v6.8.0.0\' %}',
            'currently supports only input declarations',
        ];
        yield 'path must be literal' => [
            '{% sw_deprecated input type replaced_by=\'addressType\' removed_in=\'v6.8.0.0\' %}',
            'Unexpected token',
        ];
        yield 'path segments must be identifiers' => [
            '{% sw_deprecated input \'child.bad-name\' replaced_by=\'child.name\' removed_in=\'v6.8.0.0\' %}',
            'literal root or dotted path',
        ];
        yield 'removed in is required' => [
            '{% sw_deprecated input \'type\' replaced_by=\'addressType\' %}',
            'requires the "removed_in" option',
        ];
        yield 'removed in does not accept a version without the canonical feature name' => [
            '{% sw_deprecated input \'type\' replaced_by=\'addressType\' removed_in=\'6.8.0.0\' %}',
            'canonical registered major feature flag',
        ];
        yield 'removed in does not normalize an environment variable spelling' => [
            '{% sw_deprecated input \'type\' replaced_by=\'addressType\' removed_in=\'V6_8_0_0\' %}',
            'canonical registered major feature flag',
        ];
        yield 'removed in must be registered' => [
            '{% sw_deprecated input \'type\' replaced_by=\'addressType\' removed_in=\'v9.0.0.0\' %}',
            'canonical registered major feature flag',
        ];
        yield 'removed in must be a major flag' => [
            '{% sw_deprecated input \'type\' replaced_by=\'addressType\' removed_in=\'EXPERIMENTAL_FLAG\' %}',
            'canonical registered major feature flag',
        ];
        yield 'replacement or message is required' => [
            '{% sw_deprecated input \'type\' removed_in=\'v6.8.0.0\' %}',
            'requires exactly one of "replaced_by" or "message"',
        ];
        yield 'replacement and message are mutually exclusive' => [
            '{% sw_deprecated input \'type\' replaced_by=\'addressType\' message=\'Use the replacement.\' removed_in=\'v6.8.0.0\' %}',
            'requires exactly one of "replaced_by" or "message"',
        ];
        yield 'replacement must differ' => [
            '{% sw_deprecated input \'type\' replaced_by=\'type\' removed_in=\'v6.8.0.0\' %}',
            'replacement must differ',
        ];
        yield 'message must not be empty' => [
            '{% sw_deprecated input \'type\' message=\'\' removed_in=\'v6.8.0.0\' %}',
            'must not be empty',
        ];
        yield 'unknown options are rejected' => [
            '{% sw_deprecated input \'type\' replaced_by=\'addressType\' package=\'shopware/storefront\' removed_in=\'v6.8.0.0\' %}',
            'Unknown option "package"',
        ];
        yield 'duplicate options are rejected' => [
            '{% sw_deprecated input \'type\' replaced_by=\'addressType\' removed_in=\'v6.8.0.0\' removed_in=\'v6.8.0.0\' %}',
            'declared more than once',
        ];
    }

    private function createTwig(string $template): Environment
    {
        $twig = new Environment(new ArrayLoader(['index.html.twig' => $template]));
        $twig->addExtension(new DeprecatedInputExtension());

        return $twig;
    }
}
