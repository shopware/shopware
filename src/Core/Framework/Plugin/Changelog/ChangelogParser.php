<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Changelog;

use Shopware\Core\Framework\Plugin\Exception\PluginChangelogInvalidException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class ChangelogParser
{
    /**
     * @throws PluginChangelogInvalidException
     */
    public function parseChangelog(string $path): array
    {
        $releases = [];
        $currentRelease = null;

        foreach ($this->parse($path) as $line) {
            switch ($line[0]) {
                case '#':
                    $currentRelease = $this->parseTitle($line);

                    break;
                case '-':
                case '*':
                    if (!$currentRelease) {
                        throw new PluginChangelogInvalidException($path);
                    }
                    $releases[$currentRelease][] = $this->parseItem($line);

                    break;
            }
        }

        return $releases;
    }

    private function parse(string $path): \Generator
    {
        $file = fopen($path, 'rb');

        if ($file === false) {
            throw new FileNotFoundException(null, 0, null, $path);
        }

        while ($line = fgets($file)) {
            yield $line;
        }

        fclose($file);
    }

    private function parseTitle($line): string
    {
        return mb_strtolower(trim(mb_substr($line, 1)));
    }

    private function parseItem($line): string
    {
        return trim(mb_substr($line, 1));
    }
}
