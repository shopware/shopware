<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\DocumentSourceEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class StaticDocumentSource implements DocumentSourceEntity
{
    public function __construct(
        private string $salesChannelId = 'static-sales-channel-id',
        private string $languageId = 'static-language-id',
        private ?LanguageEntity $language = null,
    ) {
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function getVersionId(): ?string
    {
        return null;
    }
}
