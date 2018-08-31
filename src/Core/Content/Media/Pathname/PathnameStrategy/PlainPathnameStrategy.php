<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname\PathnameStrategy;

class PlainPathnameStrategy implements PathnameStrategyInterface
{
    public function decode(string $path): string
    {
        return basename($path);
    }

    /**
     * {@inheritdoc}
     */
    public function encode(string $filename): string
    {
        $filename = ltrim($filename, '/');
        $pathInfo = pathinfo($filename);

        if (empty($pathInfo['extension'])) {
            return '';
        }

        if (preg_match('/(_[\d]+x[\d]+(@2x)?).(?:.*)$/', $filename)) {
            $filename = 'thumbnail/' . $filename;
        }

        return $filename;
    }

    /**
     * {@inheritdoc}
     */
    public function isEncoded(string $path): bool
    {
        return (bool) preg_match('/((media\/(?:archive|image|music|pdf|temp|unknown|video)(?:\/thumbnail)?).*\/((.+)\.(.+)))/', $path);
    }

    /**
     * Name of the strategy
     */
    public function getName(): string
    {
        return 'plain';
    }
}
