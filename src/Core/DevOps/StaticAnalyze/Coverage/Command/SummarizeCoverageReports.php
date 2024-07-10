<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Coverage\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[AsCommand(
    name: 'coverage:summarize-coverage-reports',
    description: 'Summarize unit test coverage reports from a pipeline

  In order for this command to work properly, you need to dump the composer autoloader before running it:
  $ composer dump-autoload -o'
)]
#[Package('core')]
class SummarizeCoverageReports extends Command
{
    private const TEMPLATE_FILE = __DIR__ . '/../../../Resources/templates/coverage-by-area-report.html.twig';

    public function __construct(
        private readonly string $projectDir,
        private readonly Environment $twig
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $finder = new Finder();

        $phpCoveragePerArea = [];
        $jsCoveragePerArea = [];

        foreach ($finder->in($this->projectDir . '/coverage/php')->depth(0)->directories() as $dir) {
            // We need to create a new Instance of Finder every time, because Symfony Finder doesn't reset its internal state
            $finder = new Finder();
            $xmlFiles = $finder->in($dir->getRealPath())->files()->name('cobertura.xml')->getIterator();
            $xmlFiles->rewind();
            $xml = new Crawler(file_get_contents($xmlFiles->current()->getRealPath()) ?: '');
            $phpCoveragePerArea[$dir->getFilename()] = $xml;
        }

        $finder = new Finder();
        $jsAreas = $finder->in($this->projectDir . '/coverage/js')->depth(0)->directories();

        foreach ($finder->in($this->projectDir . '/coverage/js')->depth(0)->directories() as $moduleDirectory) {
            $finder = new Finder();
            foreach ($finder->in($moduleDirectory->getRealPath())->depth(0)->directories() as $areaDirectory) {
                $finder = new Finder();
                $xmlFiles = $finder->in($areaDirectory->getRealPath())->files()->name('cobertura-coverage.xml')->getIterator();
                $xmlFiles->rewind();
                $xml = new Crawler(file_get_contents($xmlFiles->current()->getRealPath()) ?: '');
                $jsCoveragePerArea[$moduleDirectory->getFilename()][$areaDirectory->getFilename()] = $xml;
            }
        }

        $originalLoader = $this->twig->getLoader();
        $this->twig->setLoader(new ArrayLoader([
            'coverage-by-area-report.html.twig' => file_get_contents(self::TEMPLATE_FILE),
        ]));

        $jsCoverages = [];
        foreach ($jsCoveragePerArea as $coverageModuleName => $coverageModule) {
            $jsCoverages[$coverageModuleName] = $this->getCoverage($coverageModule);
        }

        $data = [
            'php' => [
                'shopware/platform' => $this->getCoverage($phpCoveragePerArea),
            ],
            'js' => $jsCoverages,
        ];

        try {
            file_put_contents('coverageSummary.json', json_encode($data));

            $html = $this->twig->render('coverage-by-area-report.html.twig', ['data' => $data]);
            file_put_contents('coverageSummary.html', $html);
        } finally {
            $this->twig->setLoader($originalLoader);
        }

        return 0;
    }

    /**
     * @param Crawler[] $xmlFiles
     *
     * @return array{area: string, percentage: string, coveredLines: int, validLines: int}[]
     */
    private function getCoverage(array $xmlFiles): array
    {
        $coverages = [];
        foreach ($xmlFiles as $area => $xml) {
            $coverage = (string) floor((float) $xml->filter('coverage')->attr('line-rate') * 10000.0) / 100.0;

            $coverages[$area] = [
                'area' => $area,
                'percentage' => $coverage >= 0 ? '' . $coverage : 'unknown',
                'coveredLines' => (int) $xml->filter('coverage')->attr('lines-covered'),
                'validLines' => (int) $xml->filter('coverage')->attr('lines-valid'),
            ];
        }

        uasort($coverages, function ($a, $b) {
            return -($a['percentage'] <=> $b['percentage']);
        });

        return $coverages;
    }
}
