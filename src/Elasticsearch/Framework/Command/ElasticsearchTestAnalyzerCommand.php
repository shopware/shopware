<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use OpenSearch\Client;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\IndexCreator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
     * @param array<string, mixed> $analysis the `elasticsearch.analysis` section (analyzer, tokenizer, char_filter, filter)
     * @param array<string, mixed> $adminAnalysis the `elasticsearch.administration.analysis` section
     */
    public function __construct(
        private readonly Client $client,
        private readonly array $analysis = [],
        private readonly array $adminAnalysis = [],
        private readonly bool $dimensionNormalize = false,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('term', InputArgument::REQUIRED)
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Also run the built-in Elasticsearch analyzers (standard, whitespace, ..., english, german, ...) for comparison.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $term = $input->getArgument('term');
        $includeBuiltIn = (bool) $input->getOption('all');

        $rows = [];
        foreach ($this->getSections($includeBuiltIn) as $headline => $analyzers) {
            $rows[] = [$headline];
            $rows[] = ['###############'];
            foreach ($analyzers as $name => $body) {
                $rows[] = [
                    'Analyzer' => $name,
                    'Tokens' => \is_string($body) ? $body : $this->analyze($body, $term),
                ];
            }

            $rows[] = [' '];
            $rows[] = [' '];
        }

        $this->io->table(['Analyzer', 'Tokens'], $rows);

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<string>>
     */
    protected function getAnalyzers(): array
    {
        return [
            'Default analyzers' => [
                'standard',
                'simple',
                'whitespace',
                'stop',
                'keyword',
                'pattern',
                'fingerprint',
            ],
            'Custom analyzers' => [],
            'Default language analyzers' => [
                'arabic',
                'armenian',
                'basque',
                'bengali',
                'brazilian',
                'bulgarian',
                'catalan',
                'cjk',
                'czech',
                'danish',
                'dutch',
                'english',
                'finnish',
                'french',
                'galician',
                'german',
                'greek',
                'hindi',
                'hungarian',
                'indonesian',
                'irish',
                'italian',
                'latvian',
                'lithuanian',
                'norwegian',
                'persian',
                'portuguese',
                'romanian',
                'russian',
                'sorani',
                'spanish',
                'swedish',
                'turkish',
                'thai',
            ],
        ];
    }

    /**
     * A single unusable analyzer must not abort the whole report, so failures are rendered
     * in place of the tokens.
     *
     * @param array<string, mixed> $body
     */
    private function analyze(array $body, mixed $term): string
    {
        $body['text'] = $term;

        try {
            /** @var array{'tokens': array{token: string}[]} $analyzed */
            $analyzed = $this->client->indices()->analyze(['body' => $body]);
        } catch (\Throwable $e) {
            return \sprintf('<error: %s>', $e->getMessage());
        }

        return implode(' ', array_column($analyzed['tokens'], 'token'));
    }

    /**
     * Builds the table sections. Each entry is either an _analyze request body or a string
     * explaining why the analyzer cannot be tested, keyed by display name.
     *
     * @return array<string, array<string, array<string, mixed>|string>>
     */
    private function getSections(bool $includeBuiltIn): array
    {
        $sections = [
            'Custom analyzers (elasticsearch.yaml)' => $this->buildCustomBodies($this->analysis, $this->dimensionNormalize),
            'Custom admin analyzers (elasticsearch.yaml)' => $this->buildCustomBodies($this->adminAnalysis, false),
        ];

        if (!$includeBuiltIn) {
            return $sections;
        }

        foreach ($this->getAnalyzers() as $headline => $analyzers) {
            if ($analyzers === []) {
                continue;
            }

            $sections[$headline] = $this->buildBuiltInBodies($analyzers);
        }

        return $sections;
    }

    /**
     * @param array<string> $names
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
     * @param array<string, mixed> $analysis
     *
     * @return array<string, array<string, mixed>|string>
     */
    private function buildCustomBodies(array $analysis, bool $dimensionNormalize): array
    {
        $analyzers = $analysis['analyzer'] ?? [];

        if (!\is_array($analyzers)) {
            return [];
        }

        if ($dimensionNormalize) {
            // Mirror what IndexCreator does when creating the index, otherwise the reported
            // tokens differ from the ones the live index actually produces.
            $analyzers = IndexCreator::withDimensionNormalize($analyzers);
        }

        $bodies = [];
        foreach ($analyzers as $name => $config) {
            if (!\is_array($config)) {
                continue;
            }

            $type = $config['type'] ?? 'custom';

            if ($type !== 'custom') {
                // _analyze accepts no `type` parameter, so a built-in analyzer type configured
                // with extra options can only be tested against an index that has it installed.
                $bodies[$name] = \sprintf('<not testable inline: analyzer type "%s">', \is_string($type) ? $type : \get_debug_type($type));

                continue;
            }

            $body = [];
            if (isset($config['tokenizer'])) {
                $body['tokenizer'] = $this->resolveReference($config['tokenizer'], $analysis['tokenizer'] ?? []);
            }
            if (isset($config['char_filter']) && \is_array($config['char_filter'])) {
                $body['char_filter'] = $this->resolveReferences($config['char_filter'], $analysis['char_filter'] ?? []);
            }
            if (isset($config['filter']) && \is_array($config['filter'])) {
                $body['filter'] = $this->resolveReferences($config['filter'], $analysis['filter'] ?? []);
            }

            $bodies[$name] = $body;
        }

        return $bodies;
    }

    /**
     * @param array<mixed> $references
     *
     * @return list<mixed>
     */
    private function resolveReferences(array $references, mixed $definitions): array
    {
        $resolved = [];
        foreach ($references as $reference) {
            $resolved[] = $this->resolveReference($reference, $definitions);
        }

        return $resolved;
    }

    /**
     * Replaces a name reference with its inline definition from the matching `analysis`
     * section (tokenizer, char_filter, filter) so _analyze can resolve it without hitting
     * a real index. Built-in names are passed through untouched.
     */
    private function resolveReference(mixed $reference, mixed $definitions): mixed
    {
        if (\is_string($reference) && \is_array($definitions) && isset($definitions[$reference])) {
            return $definitions[$reference];
        }

        return $reference;
    }
}
