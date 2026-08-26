<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;

/**
 * Contract for entities that documents can be generated from.
 *
 * @internal
 */
#[Package('after-sales')]
interface DocumentSourceEntity
{
    public function getSalesChannelId(): string;

    public function getLanguageId(): string;

    public function getLanguage(): ?LanguageEntity;

    public function getVersionId(): ?string;
}
