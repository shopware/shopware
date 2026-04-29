<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use OpenSearch\Client;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Package('framework')]
#[AsCommand(
    name: 'es:test:analyzer',
    description: 'Test the elasticsearch analyzer',
)]
class ElasticsearchTestAnalyzerCommand extends Command
{
    private ?SymfonyStyle $io = null;

    /**
     * @internal
     *
     * @param array<string, array<string, mixed>> $customAnalyzers analyzer definitions from elasticsearch.analysis.analyzer
     * @param array<string, array<string, mixed>> $customFilters filter definitions from elasticsearch.analysis.filter
     */
    public function __construct(
        private readonly Client $client,
        private readonly array $customAnalyzers = [],
        private readonly array $customFilters = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('term', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $term = $input->getArgument('term');

        $rows = [];
        foreach ($this->getSections() as $headline => $analyzers) {
            $rows[] = [$headline];
            $rows[] = ['###############'];
            foreach ($analyzers as $name => $body) {
                $body['text'] = $term;

                /** @var array{'tokens': array{token: string}[]} $analyzed */
                $analyzed = $this->client->indices()->analyze(['body' => $body]);

                $rows[] = [
                    'Analyzer' => $name,
                    'Tokens' => implode(' ', array_column($analyzed['tokens'], 'token')),
                ];
            }

            $rows[] = [' '];
            $rows[] = [' '];
        }

        $this->io->table(['Analyzer', 'Tokens'], $rows);

        return self::SUCCESS;
    }

    /**
     * Builds the table sections, each entry being an _analyze request body keyed by display name.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function getSections(): array
    {
        return [
            'Default analyzers' => $this->buildBuiltInBodies([
                'standard',
                'simple',
                'whitespace',
                'stop',
                'keyword',
                'pattern',
                'fingerprint',
            ]),
            'Custom analyzers (elasticsearch.yaml)' => $this->buildCustomBodies(),
            'Default language analyzers' => $this->buildBuiltInBodies([
                'arabic', 'armenian', 'basque', 'bengali', 'brazilian', 'bulgarian',
                'catalan', 'cjk', 'czech', 'danish', 'dutch', 'english', 'finnish',
                'french', 'galician', 'german', 'greek', 'hindi', 'hungarian',
                'indonesian', 'irish', 'italian', 'latvian', 'lithuanian', 'norwegian',
                'persian', 'portuguese', 'romanian', 'russian', 'sorani', 'spanish',
                'swedish', 'turkish', 'thai',
            ]),
        ];
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildBuiltInBodies(array $names): array
    {
        $bodies = [];
        foreach ($names as $name) {
            $bodies[$name] = ['analyzer' => $name];
        }

        return $bodies;
    }

    /**
     * Resolves each configured analyzer to an inline _analyze body so it can be tested without
     * requiring a live index that has the custom analyzer installed.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildCustomBodies(): array
    {
        $bodies = [];
        foreach ($this->customAnalyzers as $name => $config) {
            $type = $config['type'] ?? 'custom';

            if ($type !== 'custom') {
                // Built-in analyzer types (e.g. 'standard') configured with extra options — pass through.
                $bodies[$name] = $config;

                continue;
            }

            $body = [];
            if (isset($config['tokenizer'])) {
                $body['tokenizer'] = $config['tokenizer'];
            }
            if (isset($config['char_filter']) && \is_array($config['char_filter'])) {
                $body['char_filter'] = $this->resolveFilters($config['char_filter']);
            }
            if (isset($config['filter']) && \is_array($config['filter'])) {
                $body['filter'] = $this->resolveFilters($config['filter']);
            }

            $bodies[$name] = $body;
        }

        return $bodies;
    }

    /**
     * Replaces filter name references with their inline definition from elasticsearch.analysis.filter
     * so _analyze can resolve them without hitting a real index.
     *
     * @param array<mixed> $references
     *
     * @return list<mixed>
     */
    private function resolveFilters(array $references): array
    {
        $resolved = [];
        foreach ($references as $reference) {
            if (\is_string($reference) && isset($this->customFilters[$reference])) {
                $resolved[] = $this->customFilters[$reference];

                continue;
            }

            $resolved[] = $reference;
        }

        return $resolved;
    }
}
