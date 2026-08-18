<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchTestAnalyzerCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchTestAnalyzerCommand::class)]
class ElasticsearchTestAnalyzerCommandTest extends TestCase
{
    #[TestDox('The term is analyzed with every analyzer and the tokens are printed')]
    public function testPrintsTokensPerAnalyzer(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->atLeastOnce())
            ->method('analyze')
            ->willReturnCallback(function (array $params): array {
                $this->assertSame('Shopware Test', $params['body']['text']);

                return ['tokens' => [['token' => 'shopware'], ['token' => 'test']]];
            });

        $client = static::createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $commandTester = new CommandTester(new ElasticsearchTestAnalyzerCommand($client));

        static::assertSame(Command::SUCCESS, $commandTester->execute(['term' => 'Shopware Test', '--all' => true]));

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('Default analyzers', $display);
        static::assertStringContainsString('standard', $display);
        static::assertStringContainsString('shopware test', $display);
    }

    public function testCustomAnalyzersAreSentInlineWithResolvedFilters(): void
    {
        $analysis = [
            'analyzer' => [
                'sw_ngram_analyzer' => [
                    'type' => 'custom',
                    'tokenizer' => 'whitespace',
                    'filter' => ['lowercase', 'sw_ngram_filter'],
                ],
            ],
            'filter' => [
                'sw_ngram_filter' => ['type' => 'ngram', 'min_gram' => 4, 'max_gram' => 5],
            ],
        ];

        $sentBodies = [];
        $tester = new CommandTester(new ElasticsearchTestAnalyzerCommand($this->createClient($sentBodies), $analysis));
        $tester->execute(['term' => 'foobar']);

        static::assertCount(1, $sentBodies);
        static::assertSame('foobar', $sentBodies[0]['text']);
        static::assertSame('whitespace', $sentBodies[0]['tokenizer']);
        static::assertSame(
            ['lowercase', ['type' => 'ngram', 'min_gram' => 4, 'max_gram' => 5]],
            $sentBodies[0]['filter'],
        );
        static::assertStringContainsString('sw_ngram_analyzer', $tester->getDisplay());
    }

    public function testCharFiltersAndTokenizersAreResolvedFromTheirOwnSections(): void
    {
        $analysis = [
            'analyzer' => [
                'sw_technical_term_analyzer' => [
                    'type' => 'custom',
                    'tokenizer' => 'sw_edge',
                    'char_filter' => ['sw_unit_glue', 'html_strip'],
                    'filter' => ['lowercase'],
                ],
            ],
            'tokenizer' => [
                'sw_edge' => ['type' => 'edge_ngram', 'min_gram' => 2],
            ],
            'char_filter' => [
                'sw_unit_glue' => ['type' => 'pattern_replace', 'pattern' => '(\d)\s+(\D)', 'replacement' => '$1$2'],
            ],
        ];

        $sentBodies = [];
        $tester = new CommandTester(new ElasticsearchTestAnalyzerCommand($this->createClient($sentBodies), $analysis));
        $tester->execute(['term' => '100 ml']);

        static::assertCount(1, $sentBodies);
        static::assertSame(['type' => 'edge_ngram', 'min_gram' => 2], $sentBodies[0]['tokenizer']);
        static::assertSame(
            [
                ['type' => 'pattern_replace', 'pattern' => '(\d)\s+(\D)', 'replacement' => '$1$2'],
                'html_strip',
            ],
            $sentBodies[0]['char_filter'],
        );
    }

    #[TestDox('The reported chains match the live index when dimension normalization is enabled')]
    public function testDimensionNormalizeIsMirroredFromIndexCreator(): void
    {
        $sentBodies = [];
        $tester = new CommandTester(new ElasticsearchTestAnalyzerCommand($this->createClient($sentBodies), $this->dimensionAnalysis(), true));
        $tester->execute(['term' => '5 x 70']);

        static::assertCount(1, $sentBodies);
        static::assertSame(
            [
                ['type' => 'pattern_replace', 'pattern' => 'dimension'],
                ['type' => 'pattern_replace', 'pattern' => 'unit'],
            ],
            $sentBodies[0]['char_filter'],
            'sw_dimension_normalize must be prepended exactly as IndexCreator does it',
        );
    }

    public function testDimensionNormalizeIsNotAppliedWhenDisabled(): void
    {
        $sentBodies = [];
        $tester = new CommandTester(new ElasticsearchTestAnalyzerCommand($this->createClient($sentBodies), $this->dimensionAnalysis()));
        $tester->execute(['term' => '5 x 70']);

        static::assertCount(1, $sentBodies);
        static::assertSame(
            [['type' => 'pattern_replace', 'pattern' => 'unit']],
            $sentBodies[0]['char_filter'],
        );
    }

    public function testAFailingAnalyzerDoesNotAbortTheReport(): void
    {
        $analysis = [
            'analyzer' => [
                'broken_analyzer' => ['type' => 'custom', 'tokenizer' => 'nope'],
                'sw_whitespace_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace'],
            ],
        ];

        $indices = static::createStub(IndicesNamespace::class);
        $client = static::createStub(Client::class);
        $client->method('indices')->willReturn($indices);
        $indices->method('analyze')->willReturnCallback(function (array $params): array {
            if ($params['body']['tokenizer'] === 'nope') {
                throw new \RuntimeException('failed to find global tokenizer under [nope]');
            }

            return ['tokens' => [['token' => 'foo']]];
        });

        $tester = new CommandTester(new ElasticsearchTestAnalyzerCommand($client, $analysis));
        $tester->execute(['term' => 'foo']);

        static::assertStringContainsString('failed to find global tokenizer under [nope]', $tester->getDisplay());
        static::assertStringContainsString('sw_whitespace_analyzer', $tester->getDisplay());
    }

    public function testBuiltInAnalyzersAreSkippedByDefault(): void
    {
        $analysis = [
            'analyzer' => [
                'sw_whitespace_analyzer' => [
                    'type' => 'custom',
                    'tokenizer' => 'whitespace',
                    'filter' => ['lowercase'],
                ],
            ],
        ];

        $sentBodies = [];
        $tester = new CommandTester(new ElasticsearchTestAnalyzerCommand($this->createClient($sentBodies), $analysis));
        $tester->execute(['term' => 'foo']);

        static::assertCount(1, $sentBodies, 'only the configured custom analyzer should run by default');
        static::assertStringContainsString('Custom analyzers', $tester->getDisplay());
        static::assertStringNotContainsString('Default analyzers', $tester->getDisplay());
        static::assertStringNotContainsString('Default language analyzers', $tester->getDisplay());
    }

    public function testBuiltInAnalyzersIncludedWithAllFlag(): void
    {
        $sentBodies = [];
        $tester = new CommandTester(new ElasticsearchTestAnalyzerCommand($this->createClient($sentBodies), []));
        $tester->execute(['term' => 'foo', '--all' => true]);

        $analyzedNames = array_column($sentBodies, 'analyzer');

        static::assertContains('standard', $analyzedNames);
        static::assertContains('german', $analyzedNames);
        static::assertContains('english', $analyzedNames);
        static::assertStringContainsString('Default analyzers', $tester->getDisplay());
        static::assertStringContainsString('Default language analyzers', $tester->getDisplay());
    }

    public function testBuiltInSectionsStillHonourGetAnalyzersOverride(): void
    {
        $sentBodies = [];
        $command = new class($this->createClient($sentBodies)) extends ElasticsearchTestAnalyzerCommand {
            /**
             * @return array<string, array<string>>
             */
            protected function getAnalyzers(): array
            {
                return ['My analyzers' => ['my_plugin_analyzer']];
            }
        };

        $tester = new CommandTester($command);
        $tester->execute(['term' => 'foo', '--all' => true]);

        static::assertSame([['analyzer' => 'my_plugin_analyzer', 'text' => 'foo']], $sentBodies);
        static::assertStringContainsString('My analyzers', $tester->getDisplay());
    }

    /**
     * One of the analyzers `IndexCreator` injects `sw_dimension_normalize` into.
     *
     * @return array<string, mixed>
     */
    private function dimensionAnalysis(): array
    {
        return [
            'analyzer' => [
                'sw_german_technical_term_index_analyzer' => [
                    'type' => 'custom',
                    'tokenizer' => 'whitespace',
                    'char_filter' => ['sw_unit_glue'],
                ],
            ],
            'char_filter' => [
                'sw_dimension_normalize' => ['type' => 'pattern_replace', 'pattern' => 'dimension'],
                'sw_unit_glue' => ['type' => 'pattern_replace', 'pattern' => 'unit'],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $sentBodies
     */
    private function createClient(array &$sentBodies): Client
    {
        $indices = static::createStub(IndicesNamespace::class);
        $client = static::createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $indices->method('analyze')->willReturnCallback(function (array $params) use (&$sentBodies): array {
            $sentBodies[] = $params['body'];

            return ['tokens' => [['token' => 'foo']]];
        });

        return $client;
    }
}
