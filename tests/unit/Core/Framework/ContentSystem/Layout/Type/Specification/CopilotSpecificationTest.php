<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;

/**
 * @internal
 */
#[CoversClass(CopilotSpecification::class)]
class CopilotSpecificationTest extends TestCase
{
    /**
     * @param list<string> $hints
     * @param array{summary: string, hints: list<string>} $expectedSchema
     */
    #[DataProvider('returnsSchemaProvider')]
    #[TestDox('returns schema with summary and hints')]
    public function testReturnsSchemaWithSummaryAndHints(string $summary, array $hints, array $expectedSchema): void
    {
        $copilot = new CopilotSpecification($summary, $hints);

        static::assertSame($expectedSchema, $copilot->toSchema());
    }

    /**
     * @return iterable<string, array{string, list<string>, array{summary: string, hints: list<string>}}>
     */
    public static function returnsSchemaProvider(): iterable
    {
        yield 'with summary and hints' => [
            'Single product showcase.',
            ['Prefer over Sw:Product:Listing for 1-3 products.'],
            [
                'summary' => 'Single product showcase.',
                'hints' => ['Prefer over Sw:Product:Listing for 1-3 products.'],
            ],
        ];

        yield 'preserves empty summary and empty hints without modification' => [
            '',
            [],
            [
                'summary' => '',
                'hints' => [],
            ],
        ];
    }
}
