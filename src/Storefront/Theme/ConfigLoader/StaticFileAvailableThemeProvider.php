<?php
declare(strict_types=1);

namespace Shopware\Storefront\Theme\ConfigLoader;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use const JSON_THROW_ON_ERROR;

#[Package('storefront')]
class StaticFileAvailableThemeProvider extends AbstractAvailableThemeProvider
{
    final public const THEME_INDEX = 'theme-config/index.json';

    /**
     * @internal
     */
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    public function getDecorated(): AbstractAvailableThemeProvider
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.6.0 - Second parameter $activeOnly will be required in future versions.
     */
    public function load(Context $context, bool $activeOnly = false): array
    {
        if (\count(\func_get_args()) === 1) {
            Feature::triggerDeprecationOrThrow(
                'v6_6_0_0',
                sprintf(
                    'Method %s::%s is deprecated. Second parameter $activeOnly will be required in future versions.',
                    __CLASS__,
                    __METHOD__,
                )
            );
        }

        if (!$this->filesystem->fileExists(self::THEME_INDEX)) {
            throw new \RuntimeException('Cannot find theme configuration. Did you run bin/console theme:dump');
        }

        return json_decode((string) $this->filesystem->read(self::THEME_INDEX), true, 512, JSON_THROW_ON_ERROR);
    }
}
