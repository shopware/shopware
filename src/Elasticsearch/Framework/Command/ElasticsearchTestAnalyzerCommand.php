<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use OpenSearch\Client;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'es:test:analyzer',
    description: 'Test the elasticsearch analyzer',
)]
#[Package('core')]
class ElasticsearchTestAnalyzerCommand extends Command
{
    private ?ShopwareStyle $io = null;

    /**
     * @internal
     */
    public function __construct(private readonly Client $client)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addArgument('term', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);

        $term = $input->getArgument('term');

        $iteration = $this->getAnalyzers();

        $rows = [];
        foreach ($iteration as $headline => $analyzers) {
            $rows[] = [$headline];
            $rows[] = ['###############'];
            foreach ($analyzers as $analyzer) {
                /** @var array{'tokens': array{token: string}[]} $analyzed */
                $analyzed = $this->client->indices()->analyze([
                    'body' => [
                        'analyzer' => $analyzer,
                        'text' => $term,
                    ],
                ]);

                $rows[] = [
                    'Analyzer' => $analyzer,
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
}
