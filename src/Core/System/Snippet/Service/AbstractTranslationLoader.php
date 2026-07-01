<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;

#[Package('discovery')]
abstract class AbstractTranslationLoader
{
    public const TRANSLATION_DIR = '/translation';
    public const TRANSLATION_LOCALE_SUB_DIR = 'locale';

    abstract public function getDecorated(): AbstractTranslationLoader;

    abstract public function load(string $locale, Context $context, bool $activate = true): void;

    /**
     * @deprecated tag:v6.8.0 - reason:becomes-unused - Override `pluginTranslationExistsForLocale()` instead for
     * locale-aware behaviour. This method will be removed.
     */
    abstract public function pluginTranslationExists(Plugin $plugin): bool;

    public function pluginTranslationExistsForLocale(Plugin $plugin, string $locale): bool
    {
        return $this->getDecorated()->pluginTranslationExistsForLocale($plugin, $locale);
    }

    abstract public function getLocalesBasePath(): string;

    abstract public function getLocalePath(string $locale): string;
}
