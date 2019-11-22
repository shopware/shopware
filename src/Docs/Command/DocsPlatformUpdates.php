<?php declare(strict_types=1);

namespace Shopware\Docs\Command;

use Shopware\Docs\Convert\DocumentTree;
use Shopware\Docs\Convert\PlatformUpdatesDocument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class DocsPlatformUpdates extends Command
{
    private const TEMPLATE_HEADER = <<<EOD
[titleEn]: <>(Recent updates)
[__RAW__]: <>(__RAW__)

<p>Here you can find recent information about technical updates and news regarding <a href="https://github.com/shopware/platform">shopware platform</a>.</p>

<p><strong>New: Our public admin component library for easy scaffolding of your admin modules</strong></p>

<p><a href="https://component-library.shopware.com/">https://component-library.shopware.com</a></p>

EOD;

    private const TEMPLATE_MONTH = <<<EOD
<h2>%s</h2>

EOD;
    private const TEMPLATE_HEADLINE = <<<EOD
<h3>%s: %s</h3>

EOD;

    protected static $defaultName = 'docs:dump-platform-updates';

    /**
     * @var string
     */
    private $platformUpdatesPath = __DIR__ . '/../Resources/platform-updates';

    /**
     * @var string
     */
    private $targetFile = __DIR__ . '/../Resources/current/1-getting-started/40-recent-updates/__categoryInfo.md';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Dumps all Shopware 6 updates into a single file for the sync.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $files = (new Finder())
            ->in($this->platformUpdatesPath)
            ->files()
            ->sortByName()
            ->depth('0')
            ->name('*.md')
            ->exclude('_archive.md')
            ->getIterator();

        $filesInOrder = array_reverse(iterator_to_array($files));

        $filesSorted = [];

        /** @var SplFileInfo $file */
        foreach ($filesInOrder as $file) {
            $baseName = $file->getBasename('.md');

            if ($baseName === '_archive') {
                continue;
            }

            $io->write("* Rendering: $baseName \n");

            $parts = explode('-', $baseName);

            if (\count($parts) < 3) {
                throw new \RuntimeException(sprintf('File %s is invalidly named', $file->getRelativePathname()));
            }

            $date = \DateTimeImmutable::createFromFormat('Y-m-d', implode('-', \array_slice($parts, 0, 3)));

            $month = $date->format('Y-m');

            if (!isset($filesSorted[$month])) {
                $filesSorted[$month] = [];
            }

            $filesSorted[$month][] = new PlatformUpdatesDocument($date, $file, false, '');
        }

        $rendered = [self::TEMPLATE_HEADER];
        foreach ($filesSorted as $month => $documents) {
            $rendered[] = sprintf(
                self::TEMPLATE_MONTH,
                \DateTimeImmutable::createFromFormat('Y-m', $month)->format('F Y')
            );

            foreach ($documents as $document) {
                $rendered[] = sprintf(
                    self::TEMPLATE_HEADLINE,
                    $document->getDate()->format('Y-m-d'),
                    $document->getMetadata()->getTitleEn()
                );

                $rendered[] = $document->getHtml()->render(new DocumentTree())->getContents();
            }
        }

        $archivedFile = $this->platformUpdatesPath . '/_archive.md';

        if (file_exists($archivedFile)) {
            $io->note('Loading archive file');
            $rendered[] = file_get_contents($archivedFile);
        }

        $fileContents = implode(PHP_EOL, $rendered);
        file_put_contents($this->targetFile, $fileContents);

        $io->success('Done');

        return null;
    }
}
