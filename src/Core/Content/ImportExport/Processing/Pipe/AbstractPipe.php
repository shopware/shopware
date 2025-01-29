<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Pipe;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal
 */
#[Package('fundamentals@after-sales')]
abstract class AbstractPipe
{
    abstract public function in(Config $config, iterable $record): iterable;

    abstract public function out(Config $config, iterable $record): iterable;

    /**
     * @deprecated tag:v6.7.0 - will be removed with no replacement cause class becomes internal
     */
    protected function getDecorated(): AbstractPipe
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.7.0.0'),
        );
        throw new \RuntimeException('Implement getDecorated');
    }
}
