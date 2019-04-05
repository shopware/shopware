<?php declare(strict_types=1);

namespace Shopware\Docs\Command;

use Cocur\Slugify\Slugify;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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

    private const METATAG_REGEX = '/^\[(.*?)\]:\s*<>\((.*?)\)\s*?$/m';
    private $errorStack = [];
    private $warningStack = [];

    private $slugify;

    public function __construct()
    {
        parent::__construct();

        $this->slugify = new Slugify();
    }

    public function processFiles($fileContents, $inPath, $baseUrl): array
    {
        $metadata = $this->gatherMetadata($fileContents);
        $this->checkMetadata($metadata);

        if (count($this->errorStack)) {
            return [];
        }

        $metadata = $this->enrichMetadata($metadata, $inPath, $baseUrl, self::CATEGORY_SITE_FILENAME);
        $metadata = $this->enrichMetadata($metadata, $inPath, $baseUrl);
        $fileContents = $this->stripMetatags($fileContents);

        return $this->convertMarkdownFiles($fileContents, $metadata, $inPath);
    }

    public function gatherMetadata(array $fileContents): array
    {
        $metaDataDict = [];
        $keyRedefinitionWarnings = [];
        foreach ($fileContents as $file => $contents) {
            $matches = [];
            $tmpDict = [];
            if (preg_match_all(self::METATAG_REGEX, $contents, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $key = $match[1];
                    if (key_exists($key, $tmpDict)) {
                        $keyRedefinitionWarnings[] = '"' . $key . '" in file ' . $file;
                    }

                    $tmpDict[$key] = $match[2];
                }
            }
            $metaDataDict[realpath($file)] = $tmpDict;
        }

        if (count($keyRedefinitionWarnings) !== 0) {
            $this->warningStack[] = 'The following files contain multiple definitions of the same metatag:\n\t'
                . implode('\n\t', $keyRedefinitionWarnings);
        }

        return $metaDataDict;
    }

    public function checkMetadata(array $metadata): void
    {
        $filesWithoutRequiredTags = [];
        $filesWithWeirdTags = [];

        foreach ($metadata as $file => $tags) {
            foreach (self::REQUIRED_METATAGS as $requiredTag) {
                if (!key_exists($requiredTag, $tags)) {
                    $filesWithoutRequiredTags[] = [$requiredTag => $file];
                }
            }

            foreach ($tags as $tag => $value) {
                if (!in_array($tag, self::REQUIRED_METATAGS, true)
                    && !in_array($tag, self::OPTIONAL_METATAGS, true)) {
                    $filesWithWeirdTags[$file] = $tag;
                }
            }
        }

        if (count($filesWithoutRequiredTags) !== 0) {
            $message = vsprintf("%d files are missing required metatags:\n",
                [count($filesWithoutRequiredTags)]);

            foreach ($filesWithoutRequiredTags as $occurance) {
                $tag = array_key_first($occurance);
                $message .= vsprintf("\t\"%s\" in \"%s\" \n", [$tag, $occurance[$tag]]);
            }

            $this->errorStack[] = $message;
        }

        if (count($filesWithWeirdTags) !== 0) {
            $message = "The following files contain unknown metatags:\n";

            foreach ($filesWithWeirdTags as $file => $tag) {
                $message .= vsprintf("\t\"%s\" in file %s\n", [$tag, $file]);
            }

            $this->warningStack[] = $message;
        }
    }

    public function enrichMetadata(array $metadata, string $inputPath, string $baseUrl, string $filename = ''): array
    {
        //Todo: Print a warning when we are about to replace existing metadata

        foreach ($metadata as $file => &$data) {
            if ($filename !== '') {
                if (strpos($file, $filename) === false) {
                    continue;
                }
            }
            $categoryFile = pathinfo($file, PATHINFO_DIRNAME) . '/' . self::CATEGORY_SITE_FILENAME;

            $wikiUrl = $this->slugify->slugify($data['titleEn']);

            // get category name
            if (array_key_exists($categoryFile, $metadata) && $categoryFile !== $file) {
                $wikiUrl = $metadata[$categoryFile]['plainUrl'] . '/' . $wikiUrl;
            }

            $plainUrl = str_replace('//', '/', '/' . $wikiUrl);
            $data['plainUrl'] = $plainUrl;
            $wikiUrl = $baseUrl . '-en/' . $plainUrl;
            $wikiUrlDe = $baseUrl . '-de/' . $plainUrl;

            $wikiUrl = str_replace('//', '/', $wikiUrl);
            $wikiUrlDe = str_replace('//', '/', $wikiUrlDe);
            $data[self::WIKI_URL_TAG] = $wikiUrl;
            $data[self::WIKI_URL_TAG_DE] = $wikiUrlDe;

            //get priority
            $matches = [];
            if (preg_match_all('/([0-9]+)-/', $file, $matches) !== 0) {
                $data['priority'] = intval($matches[1][count($matches[1]) - 1]);
            }
        }

        return $metadata;
    }

    public function convertMarkdownFiles(array $fileContents, $metadata, string $srcPath): array
    {
        $convertedFilesMap = [];
        foreach ($fileContents as $file => $content) {
            $convertedFilename = preg_replace('/.md$/', '', $file);
            $convertedFilename = str_replace(realpath($srcPath), '', $convertedFilename);
            $convertedFilename = str_replace('//', '/', $convertedFilename);

            $html = $this->convertMarkdownToHtml($content, $file, $metadata);
            $fileMedata = array_key_exists($file, $metadata) ? $metadata[$file] : [];
            $convertedFilesMap[$convertedFilename] = [
                'content' => $html,
                'metadata' => $fileMedata,
            ];
        }

        return $convertedFilesMap;
    }

    public function stripMetatags(array $fileContents): array
    {
        $filecontentStripped = [];
        foreach ($fileContents as $file => $contents) {
            $contents = preg_replace(self::METATAG_REGEX, '', $contents);
            $filecontentStripped[$file] = $contents;
        }

        return $filecontentStripped;
    }

    public function convertMarkdownToHtml(string $contents, string $file, array &$metadata): string
    {
        $parsedown = new \ParsedownExtra();

        $html = $parsedown->parse($contents);

        //todo: replace simple code tags

        $relativeLinkReplacementRegex = '/(?:href|src)=\"(.*?)\"/m';
        $out = preg_replace_callback(
            $relativeLinkReplacementRegex,
            function ($match) use ($file, &$metadata) {
                return $this->replaceRelativeLinks($match, $file, $metadata);
            },
            $html
        );

        return $out;
    }

    public function replaceRelativeLinks(array $matches, string $file, array &$metadata): string
    {
        $link = $matches[1];
        $linkParts = explode('#', $link);
        $linkHref = $linkParts[0];
        $linkAnchor = count($linkParts) > 1 ? $linkParts[1] : '';

        $referencedFile = realpath(dirname($file) . '/' . $linkHref);

        if (preg_match('/(?:\.\/.*)\.md(?:#.*)?$/m', $linkHref) === 1) {
            if ($referencedFile !== false || strlen($linkAnchor) !== 0) {
                return $this->replaceLinkToMarkdown($matches, $file, $metadata, $linkAnchor, $referencedFile);
            }

            $this->warningStack[] = vsprintf('The markdownfile "%s" referenced in "%s" does not exist !', [$linkHref, $file]);

            return $matches[0];
        }

        if ($linkAnchor !== '') {
            return str_replace($matches[1], $this->fixLinkAnchors($linkAnchor), $matches[0]);
        }

        if ($referencedFile !== false) {
            return $this->replaceLinkToMedia($metadata, $matches, $file, $referencedFile);
        }

        return $matches[0];
    }

    public function replaceLinkToMedia(array &$metadata, array $matches, string $file, string $referencedFile): string
    {
        if (!key_exists('media', $metadata[$file])) {
            $metadata[$file]['media'] = [];
        }

        $mediaId = count($metadata[$file]['media']);
        $mediaStub = '__MEDIAITEM' . $mediaId;
        $metadata[$file]['media'][$mediaStub] = $referencedFile;

        return str_replace($matches[1], $mediaStub, $matches[0]);
    }

    public function replaceLinkToMarkdown(array $matches, string $file, array $metadata, string $linkAnchor, $referencedFile): string
    {
        // If the link contains a anchor, correct it
        if ($linkAnchor !== '') {
            // If a #-anchor contains a dot '.', replace it with a dash '-'
            // Also convert the string to lowercase, as this is the convention for anchors
            $linkAnchor = $this->fixLinkAnchors($linkAnchor);
        }

        if ($referencedFile !== false && is_file($referencedFile)) {
            // If the link is another markdown file, check if we previously extracted a wikiUrl from it
            if (!key_exists($referencedFile, $metadata) || !key_exists(self::WIKI_URL_TAG, $metadata[$referencedFile])) {
                $this->errorStack[]
                    = vsprintf('Unable to resolve wikiUrl for the file %s referenced in %s. The resulting link will be broken.', [$referencedFile, $file]);
                // Return the original href tag
                return $matches[0];
            }

            // Resolve the markdown filepath and append the anchor if there is any
            $resolvedLink = $metadata[$referencedFile][self::WIKI_URL_TAG] . $linkAnchor;

            return str_replace($matches[1], $resolvedLink, $matches[0]);
        }

        $this->warningStack[] = vsprintf('The markdownfile "%s" referenced in "%s" does not exist !', [$matches[1], $file]);

        // If there is no filename preceding the anchor, just return the anchor
        if ($linkAnchor !== '') {
            return str_replace($matches[1], $linkAnchor, $matches[0]);
        }

        // Nothing to be done
        return $matches[0];
    }

    public function writeConvertedMarkdownFiles(array $convertedFiles, string $outPath): void
    {
        foreach ($convertedFiles as $convertedFile => $convertedInformation) {
            $convertedFile = $outPath . '/' . $convertedFile . '.html';
            $path = pathinfo($convertedFile, PATHINFO_DIRNAME) . '/';
            if (!file_exists($path)) {
                if (!mkdir($path, 0777, true) && !is_dir($path)) {
                    $this->errorStack[] = sprintf('Could not create parent directory "%s" for file "%s".', $path, $convertedFile);
                }
            }
            $ret = file_put_contents($convertedFile, $convertedInformation['content']);
            if ($ret === false) {
                $this->errorStack[] = sprintf('Could not create or write file "%s".', $convertedFile);
            }
        }
    }

    public function removeBlacklistedFiles(array $files, array $conditions): array
    {
        foreach ($conditions as $condition) {
            $files = preg_grep($condition, $files, PREG_GREP_INVERT);
        }

        return $files;
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
        $files = $this->listMarkdownFiles($inPath);
        $output->writeln('Removing blacklisted files following ' . count($blacklist) . ' rules...');
        $files = $this->removeBlacklistedFiles($files, $blacklist);
        $output->writeln('Found ' . count($files) . ' potential doc files', OutputInterface::VERBOSITY_VERBOSE);
        $fileContents = $this->readAllFiles($files);
        $output->writeln('Read ' . count(array_keys($fileContents)) . ' markdown files');

        $converted = $this->processFiles($fileContents, $inPath, $baseUrl);

        if ($outPath !== null) {
            $this->writeConvertedMarkdownFiles($converted, $outPath);
        }

        $warningStyle = new OutputFormatterStyle('yellow');
        $output->getFormatter()->setStyle('warn', $warningStyle);

        $errStyle = new OutputFormatterStyle('red');
        $output->getFormatter()->setStyle('err', $errStyle);

        foreach ($this->warningStack as $warning) {
            $output->writeln('<warn>WARNING: ' . $warning . '</>');
        }

        if (\count($this->errorStack) !== 0) {
            foreach ($this->errorStack as $error) {
                $output->writeln('<err>ERROR: ' . $error . '</>');
            }

            throw new \RuntimeException($this->getName() . " encounted one or more errors.\n"
                . "This is most likely a problem with your markdown files.\n"
                . 'More information can possibly be found above.');
        }

        if ($isSync) {
            try {
                $jsonContents = json_encode($converted);
                $arguments = new ArrayInput(['content' => $jsonContents]);

                $syncCommand = $this->getApplication()->find('docs:sync');
                $syncCommand->run($arguments, $output);
            } catch (CommandNotFoundException $e) {
                $output->writeln('<err>ERROR:  Sync Command not registered </>');
            }
        }
    }

    protected function listMarkdownFiles(string $basepath): array
    {
        $matches = new \RegexIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($basepath)
            ),
            '/.*\.md$/mi', \RegexIterator::GET_MATCH);
        $files = [];
        foreach ($matches as $match) {
            $files[] = realpath($match[0]);
        }

        return $files;
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

    private function fixLinkAnchors(string $anchor): string
    {
        return '#' . strtolower(str_replace('.', '-', $anchor));
    }
}
