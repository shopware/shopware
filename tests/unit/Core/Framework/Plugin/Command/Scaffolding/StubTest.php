<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;

/**
 * @internal
 */
#[CoversClass(Stub::class)]
class StubTest extends TestCase
{
    public function testTemplateConstructor(): void
    {
        $destinationPath = '/path/to/destination';
        $sourcePath = __DIR__ . '/test-with-params.stub';

        $stub = Stub::template($destinationPath, $sourcePath);

        static::assertEquals($destinationPath, $stub->getPath());
        static::assertEquals(file_get_contents(__DIR__ . '/test-with-params.stub'), $stub->getContent());
    }

    public function testRawConstructor(): void
    {
        $destinationPath = '/path/to/destination';
        $content = 'Raw Content';

        $stub = Stub::raw($destinationPath, $content);

        static::assertEquals($destinationPath, $stub->getPath());
        static::assertEquals($content, $stub->getContent());
    }

    /**
     * @param array<string, string> $params
     */
    #[DataProvider('contentProvider')]
    public function testGetContent(string $type, string $content, ?string $expectedContent, array $params = []): void
    {
        $stub = new Stub('/path/to/destination', $content, $type, $params);

        static::assertEquals($expectedContent, $stub->getContent());
    }

    public static function contentProvider(): \Generator
    {
        yield 'content without params raw' => [
            'type' => Stub::TYPE_RAW,
            'content' => 'Hello John, how are you?',
            'expectedContent' => 'Hello John, how are you?',
        ];

        yield 'content with params raw' => [
            'type' => Stub::TYPE_RAW,
            'content' => 'Hello {{ param1 }}, how are you?',
            'expectedContent' => 'Hello John, how are you?',
            'params' => ['param1' => 'John'],
        ];

        yield 'content without params template' => [
            'type' => Stub::TYPE_TEMPLATE,
            'content' => __DIR__ . '/test-without-params.stub',
            'expectedContent' => "Hello John, how are you?\n",
        ];

        yield 'content with params template' => [
            'type' => Stub::TYPE_TEMPLATE,
            'content' => __DIR__ . '/test-with-params.stub',
            'expectedContent' => "Hello John, how are you?\n",
            'params' => ['param1' => 'John'],
        ];
    }
}
