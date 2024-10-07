<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Language\Stubs;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\SalesChannelLanguageLoader;

/**
 * @internal
 */
#[Package('core')]
class StaticSalesChannelLanguageLoader extends SalesChannelLanguageLoader
{
    /**
     * @param array<string, array<string>> $languages
     */
    public function __construct(private readonly array $languages = [])
    {
    }

    /**
     * {@inheritDoc}
     */
    public function loadLanguages(): array
    {
        return $this->languages;
    }
}
