<?php declare(strict_types=1);

namespace Shopware\Docs\Command;

use Cocur\Slugify\Slugify;
use Shopware\Docs\Convert\Document;
use Shopware\Docs\Convert\DocumentTree;
use Shopware\Docs\Convert\WikiApiService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ConvertMarkdownDocsCommand extends Command
{
    public const CATEGORY_SITE_FILENAME = '__categoryInfo.md';
    public const WIKI_URL_TAG = 'wikiUrl';
    public const WIKI_URL_TAG_DE = 'wikiUrlDe';
    public const REQUIRED_METATAGS = ['titleEn'];
    public const OPTIONAL_METATAGS = [
        self::WIKI_URL_TAG,
        'metaDescription',
        'titleDe',
        'isActive',
    ];
    private const CREDENTIAL_PATH = __DIR__ . '/wiki.secret';

    private const METATAG_REGEX = '/^\[(.*?)\]:\s*<>\((.*?)\)\s*?$/m';
    private $errorStack = [];
    private $warningStack = [];

    private $slugify;

    public function __construct()
    {
        parent::__construct();

        $this->slugify = new Slugify();
    }

    public function getErrorStack(): array
    {
        return $this->errorStack;
    }

    public function getWarningStack(): array
    {
        return $this->warningStack;
    }

    protected function configure(): void
    {
        $this->setName('docs:convert')
            ->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'The path to parse for markdown files.', './platform/src/Docs/Resources/current/')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'The path in which the resulting hmtl files will be saved.')
            ->addOption('blacklist', 'b', InputOption::VALUE_REQUIRED, 'Path to a file containing blacklisted items (files or paths). Each line must contain one entry.', './platform/src/Docs/Resources/current/article.blacklist')
            ->addOption('baseurl', 'u', InputOption::VALUE_REQUIRED, '', '/shopware-platform')
            ->addOption('sync', 's', InputOption::VALUE_NONE)
            ->setDescription('Converts Markdown to Wikihtml');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inPath = $input->getOption('input');
        $outPath = $input->getOption('output');
        $baseUrl = $input->getOption('baseurl');
        $isSync = $input->getOption('sync');
        $blackListFile = $input->getOption('blacklist');

        $blacklist = [];
        if ($blackListFile !== null) {
            $blacklistContents = file_get_contents($blackListFile);
            $blacklist = explode("\n", $blacklistContents);
        }

        $output->writeln('Scanning \"' . $inPath . '" for .md files ...');
        $tree = $this->loadDocuments($inPath, $baseUrl);
        $output->writeln('Read ' . count($tree->getAll()) . ' markdown files');

        if ($outPath === null) {
            throw new \RuntimeException('No output path specified');
        }

        $fs = new Filesystem();

        /** @var Document $document */
        foreach ($tree->getAll() as $document) {
            $path = $outPath . '/' . $document->getFile()->getRelativePath();

            $htmlFile = $path . '/' . $document->getFile()->getBasename('.md') . '.html';
            $phpFile = $path . '/' . $document->getFile()->getBasename('.md') . '.php';

            $fs->mkdir($outPath);
            $fs->dumpFile($htmlFile, $document->getHtml()->render($tree)->getContents());
            $fs->dumpFile($phpFile, '<?php return ' . var_export($document->getMetadata()->toArray($tree), true) . ';');
        }

        $credentialsContents = (file_get_contents(self::CREDENTIAL_PATH));
        $credentials = json_decode($credentialsContents, true);
        $token = $credentials['token'];
        $server = $credentials['url'];
        $rootCategory = $credentials['rootCategoryId'];

        $syncService = new WikiApiService($token, $server, $rootCategory);
        $syncService->syncFilesWithServer($tree);
    }

    protected function readAllFiles(array $files): array
    {
        $allContents = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === '') {
                continue;
            }
            $allContents[$file] = $content;
        }

        return $allContents;
    }

    private function loadDocuments(string $fromPath, string $baseUrl): DocumentTree
    {
        $files = (new Finder())
            ->files()
            ->in($fromPath)
            ->name('*.md');

        $documents = [];

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $documents[$file->getRelativePathname()] = new Document(
                $file,
                $file->getFilename() === self::CATEGORY_SITE_FILENAME,
                $baseUrl
            );
        }

        //compile into tree
        $tree = new DocumentTree();
        /** @var Document $document */
        foreach ($documents as $path => $document) {
            if ($document->isCategory()) {
                $parentPath = dirname($document->getFile()->getRelativePath()) . '/' . self::CATEGORY_SITE_FILENAME;
            } else {
                $parentPath = $document->getFile()->getRelativePath() . '/' . self::CATEGORY_SITE_FILENAME;
            }

            $tree->add($document);

            //find parent
            if (!isset($documents[$parentPath])) {
                $tree->addRoot($document);
                continue;
            }

            /** @var Document $parent */
            $parent = $documents[$parentPath];
            $document->setParent($parent);
            $parent->addChild($document);
        }

        return $tree;
    }
}
