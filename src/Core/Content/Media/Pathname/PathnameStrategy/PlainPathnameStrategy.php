<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname\PathnameStrategy;

class PlainPathnameStrategy implements PathnameStrategyInterface
{
    /**
     * {@inheritdoc}
     */
    public function encode(string $filename, string $id): string
    {
        return ltrim($filename, '/');
    }

    /**
     * Name of the strategy
     */
    public function getName(): string
    {
        return 'plain';
    }
}
