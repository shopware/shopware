<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

use Symfony\Component\Finder\SplFileInfo;

class DocsParsedownExtra extends \ParsedownExtra
{
    public const START_MARK = 'namespace %s {';

    public const STOPMARK = '} // code-example-end';

    /**
     * @var SplFileInfo
     */
    private $sourceFile;

    /**
     * @var string|null
     */
    private $content;

    /**
     * @throws \Exception
     */
    public function __construct(SplFileInfo $sourceFile)
    {
        parent::__construct();
        $this->sourceFile = $sourceFile;
    }

    protected function blockFencedCode($Line): ?array
    {
        $Block = parent::blockFencedCode($Line);

        $parts = explode(':', $Line['body']);

        if ($Block && \count($parts) > 1) {
            $this->docsLoadIncludeData($parts);
        }

        $Block = parent::blockFencedCode($Line);

        return $Block;
    }

    protected function blockFencedCodeComplete($Block)
    {
        $Block = parent::blockFencedCodeComplete($Block);

        if ($this->content) {
            $Block['element']['text']['text'] = $this->content;
            $this->content = null;
        }

        return $Block;
    }

    protected function docsLoadIncludeData(array $parts): void
    {
        [$includeFile, $namespace] = $this->docsExtractFileAndNamespace($parts);

        $slicedLines = $this->docsExtractContentsFromIncludeFile($includeFile, $namespace);
        $this->docsRenderIncludeContents($slicedLines);
    }

    protected function docsExtractFileAndNamespace(array $parts): array
    {
        $includeParts = explode('#', $parts[1]);

        $includeFile = \dirname($this->sourceFile->getRealPath()) . mb_substr($includeParts[0], 1);

        $namespace = $includeParts[1];

        if (!file_exists($includeFile)) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to load %s referenced in %s',
                    $includeFile,
                    $this->sourceFile->getRealPath()
                )
            );
        }

        return [$includeFile, $namespace];
    }

    protected function docsExtractContentsFromIncludeFile($includeFile, $namespace): array
    {
        $start = false;
        $stop = false;

        $lines = file($includeFile);

        foreach ($lines as $lineNumber => $line) {
            if (mb_strpos($line, sprintf(self::START_MARK, $namespace)) === 0) {
                $start = 1 + $lineNumber;
            }

            if ($start !== false && $stop === false && mb_strpos($line, self::STOPMARK) === 0) {
                $stop = $lineNumber;
            }
        }

        if ($start === false) {
            throw new \RuntimeException(sprintf('Unable to find the start of %s in %s', $namespace, $includeFile));
        }

        if ($stop === false) {
            throw new \RuntimeException(sprintf('Unable to find the stop of %s in %s', $namespace, $includeFile));
        }

        return \array_slice($lines, $start, $stop - $start);
    }

    protected function docsRenderIncludeContents(array $slicedLines): void
    {
        $reIntendedLines = array_map(static function (string $line) {
            return mb_substr($line, 4);
        }, $slicedLines);

        $implodedLines = implode('', $reIntendedLines);

        $this->content = \PHP_EOL . '<?php declare(strict_types=1);' . \PHP_EOL . $implodedLines;
    }
}
