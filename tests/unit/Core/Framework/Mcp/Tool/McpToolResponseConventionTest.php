<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResponse::class)]
class McpToolResponseConventionTest extends TestCase
{
    public function testAllMcpToolsUseResponseTrait(): void
    {
        $toolDir = \dirname(__DIR__, 6) . '/src/Core/Framework/Mcp/Tool';

        $finder = (new Finder())
            ->files()
            ->in($toolDir)
            ->name('*Tool.php')
            ->notName('McpToolResponse.php');

        $violations = [];

        foreach ($finder as $file) {
            $className = 'Shopware\\Core\\Framework\\Mcp\\Tool\\' . $file->getBasename('.php');

            if (!class_exists($className)) {
                continue;
            }

            $ref = new \ReflectionClass($className);

            $hasMcpToolAttribute = $ref->getAttributes(McpTool::class) !== [];

            if (!$hasMcpToolAttribute) {
                continue;
            }

            $usesTrait = \in_array(McpToolResponse::class, $ref->getTraitNames(), true);

            if (!$usesTrait) {
                $violations[] = $className;
            }
        }

        static::assertSame(
            [],
            $violations,
            \sprintf(
                "The following MCP tools do not use the McpToolResponse trait:\n- %s",
                implode("\n- ", $violations)
            )
        );
    }

    public function testSuccessWithoutMetaOmitsMetaKey(): void
    {
        $helper = new McpToolResponseTestHelper();
        $result = json_decode($helper->callSuccess(['key' => 'value']), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame(['key' => 'value'], $result['data']);
        static::assertArrayNotHasKey('_meta', $result);
    }

    public function testSuccessWithMetaIncludesMetaKey(): void
    {
        $helper = new McpToolResponseTestHelper();
        $result = json_decode($helper->callSuccess(['x' => 1], ['total' => 5]), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(5, $result['_meta']['total']);
    }

    public function testErrorReturnsCorrectStructure(): void
    {
        $helper = new McpToolResponseTestHelper();
        $result = json_decode($helper->callError('Something broke'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertSame('Something broke', $result['error']);
        static::assertArrayNotHasKey('data', $result);
    }

    public function testOversizedListResponseIsTruncatedToFiveItems(): void
    {
        $helper = new McpToolResponseTestHelper();

        $largeList = [];
        for ($i = 0; $i < 500; ++$i) {
            $largeList[] = ['data' => str_repeat('x', 500)];
        }

        $result = json_decode($helper->callSuccess($largeList), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertCount(5, $result['data']);
        static::assertTrue($result['_meta']['truncated']);
        static::assertArrayHasKey('truncatedMessage', $result['_meta']);
    }

    public function testDryRunSwallowsRollBackException(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('rollBack')->willThrowException(new \RuntimeException('rollback failed'));

        $helper = new McpToolResponseTestHelper();
        $result = json_decode($helper->callDryRun($connection, fn () => '{"success":true}'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
    }

    public function testOversizedAssocResponseStillTooLargeClearsData(): void
    {
        $helper = new McpToolResponseTestHelper();

        $largeAssoc = ['content' => str_repeat('x', 200_000)];

        $result = json_decode($helper->callSuccess($largeAssoc), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame([], $result['data']);
        static::assertTrue($result['_meta']['truncated']);
        static::assertStringContainsString('still too large', $result['_meta']['truncatedMessage']);
    }
}

/**
 * @internal
 */
class McpToolResponseTestHelper
{
    use McpToolResponse;

    /**
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, mixed> $meta
     */
    public function callSuccess(array $data, array $meta = []): string
    {
        return $this->success($data, $meta);
    }

    public function callError(string $message): string
    {
        return $this->error($message);
    }

    /**
     * @param callable(): string $operation
     */
    public function callDryRun(Connection $connection, callable $operation): string
    {
        return $this->executeWithDryRun($connection, $operation);
    }
}
