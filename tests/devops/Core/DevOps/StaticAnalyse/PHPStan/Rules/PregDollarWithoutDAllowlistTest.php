<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\PregDollarWithoutDRule;
use Shopware\Core\DevOps\Test\AnnotationTagVersionSchema;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\App\Command\CreateAppCommandTest;

/**
 * The files allowlisted for the unresolved identifier of PregDollarWithoutDRule are the calls the rule cannot read.
 * Every one of them needs an entry here: a probe that runs the real pattern against a sample with a trailing
 * newline, a pointer to the test holding that probe, or the reason no probe applies.
 *
 * @internal
 */
#[Package('framework')]
class PregDollarWithoutDAllowlistTest extends TestCase
{
    /**
     * Maps each allowlisted file to its coverage. `probes` lists enum cases exposing the pattern together with a
     * matching sample, `coveredBy` points to the test method exercising the pattern through its public surface,
     * `reason` explains why the pattern is out of scope.
     *
     * @var array<string, array{probes?: list<array{AnnotationTagVersionSchema, string}>, coveredBy?: array{class-string, string}, reason?: string}>
     */
    private const REGISTRY = [
        'src/Core/Framework/App/Command/CreateAppCommand.php' => [
            'coveredBy' => [CreateAppCommandTest::class, 'testCommandFailsWithInvalidInput'],
        ],
        'src/Core/DevOps/Test/AnnotationTagTester.php' => [
            'probes' => [
                [AnnotationTagVersionSchema::PLATFORM_VERSION_SCHEMA, 'v6.4.0.0'],
                [AnnotationTagVersionSchema::PLATFORM_DEPRECATION_SCHEMA, 'v6.5.0'],
                [AnnotationTagVersionSchema::PLATFORM_MAJOR_SCHEMA, 'v6.8.0.0'],
                [AnnotationTagVersionSchema::MANIFEST_VERSION_SCHEMA, 'v1.0'],
            ],
        ],
        'src/Core/Framework/Adapter/Twig/Extension/PcreExtension.php' => [
            'reason' => 'the pattern is written by the template author',
        ],
        'src/Core/Maintenance/Staging/Handler/StagingSalesChannelHandler.php' => [
            'reason' => 'the patterns come from the staging configuration',
        ],
        'src/Core/Framework/ShopwareHttpException.php' => [
            'reason' => 'the patterns are built per message placeholder and are not anchored',
        ],
        'src/Core/System/Snippet/Files/SnippetFileLoader.php' => [
            'reason' => 'the patterns are built from a template that carries D and match filesystem paths',
        ],
    ];

    public function testEveryAllowlistedFileIsRegistered(): void
    {
        $registered = array_keys(self::REGISTRY);
        sort($registered);

        static::assertSame($this->readAllowlistedPaths(), $registered);
    }

    #[DataProvider('probes')]
    public function testPatternRejectsTrailingNewline(AnnotationTagVersionSchema $schema, string $sample): void
    {
        static::assertSame(1, preg_match($schema->pattern(), $sample));
        static::assertSame(0, preg_match($schema->pattern(), $sample . "\n"));
    }

    #[DataProvider('pointers')]
    public function testPointedTestExists(string $class, string $method): void
    {
        static::assertTrue(method_exists($class, $method), \sprintf('%s::%s() does not exist', $class, $method));
    }

    public static function probes(): \Generator
    {
        foreach (self::REGISTRY as $file => $entry) {
            foreach ($entry['probes'] ?? [] as [$schema, $sample]) {
                yield $file . ' ' . $schema->name => [$schema, $sample];
            }
        }
    }

    public static function pointers(): \Generator
    {
        foreach (self::REGISTRY as $file => $entry) {
            if (isset($entry['coveredBy'])) {
                yield $file => $entry['coveredBy'];
            }
        }
    }

    /**
     * The allowlist entries keep `identifier` directly above `path`, see the comment in phpstan.neon.dist.
     *
     * @return list<string>
     */
    private function readAllowlistedPaths(): array
    {
        $config = (string) file_get_contents(\dirname(__DIR__, 7) . '/phpstan.neon.dist');

        preg_match_all(
            '/identifier: ' . preg_quote(PregDollarWithoutDRule::IDENTIFIER_UNRESOLVED, '/') . '\n\s*path: (\S+)/',
            $config,
            $matches
        );

        $paths = $matches[1];
        sort($paths);

        return $paths;
    }
}
