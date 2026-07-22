<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format;

use Shopware\Core\Content\ImportExportV2\ImportExportV2Exception;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class FormatRegistry
{
    /**
     * @var array<string, FormatInterface>
     */
    private array $formats = [];

    /**
     * @param iterable<FormatInterface> $formats
     */
    public function __construct(iterable $formats)
    {
        foreach ($formats as $format) {
            $this->formats[$format->getName()] = $format;
        }
    }

    public function get(string $name): FormatInterface
    {
        return $this->formats[$name] ?? throw ImportExportV2Exception::formatNotFound($name);
    }
}
