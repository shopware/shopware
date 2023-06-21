<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Coverage\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\Finder;

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
    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $finder = new Finder();

        $phpCoveragePerArea = [];
        $jsCoveragePerArea = [];

        foreach ($finder->in($this->projectDir . '/coverage/php')->depth(0)->directories() as $dir) {
            // We need to create a new Instance of Finder every time, because Symfony Finder doesn't reset it's internal state
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

        $style = '<style lang="css">html,body{box-sizing:border-box;margin:0;padding:0;line-height:1.6;font-size:16px;font-family:"Trebuchet MS",Arial,Helvetica,sans-serif;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;color:#333}#content{margin:0 auto;padding:30px 0}h1,h2,h3,h4,h5,h6{margin-top:50px;margin-bottom:15px;line-height:1.1}h1,h2{font-size:30px}h3{font-size:21px}table{display:block;width:100%;overflow:auto;word-break:normal;word-break:keep-all}table th{font-weight:bold;text-align:left}table th,table td{padding:6px 26px 6px 13px;border:1px solid #ddd}table tr{background-color:#fff;border-top:1px solid #ccc}table tr:nth-child(2n){background-color:#f8f8f8}</style>';
        $summaryHtml = "<html><head><title>Unit coverage summary</title>$style</head><body><div id='content'>";
        $summaryHtml .= '<h2>PHP Coverage per area</h2>';

        $summaryHtml .= $this->generateTable($phpCoveragePerArea);

        $summaryHtml .= '<h2>JS Coverage per area</h2>';
        foreach ($jsCoveragePerArea as $coverageModuleName => $coverageModule) {
            $summaryHtml .= "<h3>$coverageModuleName</h3>";
            $summaryHtml .= $this->generateTable($coverageModule);
        }

        $summaryHtml .= '</div></body></html>';

        file_put_contents('coverageSummary.html', $summaryHtml);

        return 0;
    }

    /**
     * @param \Symfony\Component\DomCrawler\Crawler[] $xmlFiles
     */
    private function generateTable(array $xmlFiles): string
    {
        $output = '<table borders="0"><tr><th>Area</th><th>Line coverage</th></tr>';
        foreach ($xmlFiles as $area => $xml) {
            $coverage = floor((float) $xml->filter('coverage')->attr('line-rate') * 10000.0) / 100.0;
            $linesCovered = $xml->filter('coverage')->attr('lines-covered');
            $linesValid = $xml->filter('coverage')->attr('lines-valid');
            $coverage = $coverage >= 0 ? '' . $coverage : 'unknown';
            $output .= "<tr><td>$area </td><td>$coverage% ($linesCovered / $linesValid)</td></tr>";
        }
        $output .= '</table>';

        return $output;
    }
}
