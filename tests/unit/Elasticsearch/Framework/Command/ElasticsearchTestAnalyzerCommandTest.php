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

        static::assertSame(Command::SUCCESS, $commandTester->execute(['term' => 'Shopware Test']));

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('Default analyzers', $display);
        static::assertStringContainsString('standard', $display);
        static::assertStringContainsString('shopware test', $display);
    }

    public function testCustomAnalyzersAreSentInlineWithResolvedFilters(): void
    {
        $analyzers = [
            'sw_ngram_analyzer' => [
                'type' => 'custom',
                'tokenizer' => 'whitespace',
                'filter' => ['lowercase', 'sw_ngram_filter'],
            ],
        ];

        $filters = [
            'sw_ngram_filter' => ['type' => 'ngram', 'min_gram' => 4, 'max_gram' => 5],
        ];

        $indices = $this->createMock(IndicesNamespace::class);
        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $sentBodies = [];
        $indices->method('analyze')->willReturnCallback(function (array $params) use (&$sentBodies): array {
            $sentBodies[] = $params['body'];

            return ['tokens' => [['token' => 'foo']]];
        });

        $tester = new CommandTester(new ElasticsearchTestAnalyzerCommand($client, $analyzers, $filters));
        $tester->execute(['term' => 'foobar']);

        $customBody = null;
        foreach ($sentBodies as $body) {
            if (($body['tokenizer'] ?? null) === 'whitespace') {
                $customBody = $body;

                break;
            }
        }

        static::assertNotNull($customBody, 'expected custom analyzer body to be sent');
        static::assertSame('foobar', $customBody['text']);
        static::assertSame(
            ['lowercase', ['type' => 'ngram', 'min_gram' => 4, 'max_gram' => 5]],
            $customBody['filter'],
        );
        static::assertStringContainsString('sw_ngram_analyzer', $tester->getDisplay());
    }

    public function testBuiltInAnalyzersAreSentByName(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $sentBodies = [];
        $indices->method('analyze')->willReturnCallback(function (array $params) use (&$sentBodies): array {
            $sentBodies[] = $params['body'];

            return ['tokens' => [['token' => 'foo']]];
        });

        $tester = new CommandTester(new ElasticsearchTestAnalyzerCommand($client, [], []));
        $tester->execute(['term' => 'foo']);

        $standard = null;
        foreach ($sentBodies as $body) {
            if (($body['analyzer'] ?? null) === 'standard') {
                $standard = $body;

                break;
            }
        }

        static::assertNotNull($standard);
        static::assertSame('foo', $standard['text']);
    }

    public function testLanguageAnalyzersAreSkippedByDefault(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $analyzedNames = [];
        $indices->method('analyze')->willReturnCallback(function (array $params) use (&$analyzedNames): array {
            if (isset($params['body']['analyzer'])) {
                $analyzedNames[] = $params['body']['analyzer'];
            }

            return ['tokens' => []];
        });

        $tester = new CommandTester(new ElasticsearchTestAnalyzerCommand($client, [], []));
        $tester->execute(['term' => 'foo']);

        static::assertContains('standard', $analyzedNames);
        static::assertNotContains('german', $analyzedNames);
        static::assertNotContains('english', $analyzedNames);
        static::assertStringNotContainsString('Default language analyzers', $tester->getDisplay());
    }

    public function testLanguageAnalyzersIncludedWithFlag(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $analyzedNames = [];
        $indices->method('analyze')->willReturnCallback(function (array $params) use (&$analyzedNames): array {
            if (isset($params['body']['analyzer'])) {
                $analyzedNames[] = $params['body']['analyzer'];
            }

            return ['tokens' => []];
        });

        $tester = new CommandTester(new ElasticsearchTestAnalyzerCommand($client, [], []));
        $tester->execute(['term' => 'foo', '--with-language-analyzers' => true]);

        static::assertContains('german', $analyzedNames);
        static::assertContains('english', $analyzedNames);
        static::assertStringContainsString('Default language analyzers', $tester->getDisplay());
    }
}
