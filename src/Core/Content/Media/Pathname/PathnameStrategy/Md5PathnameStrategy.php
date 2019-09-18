<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname\PathnameStrategy;

use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;

class Md5PathnameStrategy implements PathnameStrategyInterface
{
    /**
     * @var array
     */
    private $blacklist = [
        'ad' => 'g0',
    ];

    public function decode(string $path): string
    {
        return basename($path);
    }

    /**
     * @throws EmptyMediaFilenameException
     */
    public function encode(string $filename): string
    {
        if (empty($filename)) {
            throw new EmptyMediaFilenameException();
        }

        if ($this->isEncoded($filename)) {
            return $filename;
        }

        $md5hash = md5($filename);

        $md5hashSlices = \array_slice(str_split($md5hash, 2), 0, 3);
        $md5hashSlices = array_map(
            function ($slice) {
                return array_key_exists($slice, $this->blacklist) ? $this->blacklist[$slice] : $slice;
            },
            $md5hashSlices
        );

        return implode('/', $md5hashSlices) . '/' . $filename;
    }

    public function isEncoded(string $path): bool
    {
        if ($this->hasBlacklistParts($path)) {
            return false;
        }

        return (bool) preg_match("/(?:([0-9a-g]{2}\/[0-9a-g]{2}\/[0-9a-g]{2}\/))((.+)\.(.+))/", $path);
    }

    public function getName(): string
    {
        return 'md5';
    }

    private function hasBlacklistParts(string $path): bool
    {
        foreach ($this->blacklist as $key => $value) {
            if (mb_strpos($path, '/' . $key . '/') !== false) {
                return true;
            }
        }

        return false;
    }
}
