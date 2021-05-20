<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Elasticsearch\Client;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ElasticsearchTestAnalyzerCommand extends Command
{
    protected static $defaultName = 'es:test:analyzer';

    private Client $client;

    private ?ShopwareStyle $io;

    public function __construct(Client $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addArgument('term', InputArgument::REQUIRED)
            ->setDescription('Allows to test an elasticsearch analyzer');
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
                /** @var array{'tokens': array} $analyzed */
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
