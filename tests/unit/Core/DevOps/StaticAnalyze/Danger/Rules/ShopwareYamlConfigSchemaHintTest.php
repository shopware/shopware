<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\ShopwareYamlConfigSchemaHint;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwareYamlConfigSchemaHint::class)]
class ShopwareYamlConfigSchemaHintTest extends TestCase
{
    /**
     * @param list<string> $touchedFiles
     */
    #[TestDox('Warns when shopware.yaml changes without a config-schema.json change')]
    #[DataProvider('touchedFilesProvider')]
    public function testConfigSchemaSync(array $touchedFiles, bool $expectWarning): void
    {
        $files = array_map(static fn (string $name): StubFile => new StubFile($name), $touchedFiles);
        $context = new Context(new StubPlatform(new StubPullRequest($files)));

        (new ShopwareYamlConfigSchemaHint())($context);

        static::assertSame($expectWarning, $context->hasWarnings());
        if ($expectWarning) {
            static::assertStringContainsString('config-schema.json', $context->getWarnings()[0]);
        }
    }

    public static function touchedFilesProvider(): \Generator
    {
        yield 'shopware.yaml without schema update warns' => [['config/packages/shopware.yaml'], true];
        yield 'shopware.yaml with schema update passes' => [['config/packages/shopware.yaml', 'config-schema.json'], false];
        yield 'schema-only change passes' => [['config-schema.json'], false];
        yield 'unrelated yaml change passes' => [['config/packages/framework.yaml'], false];
    }
}
