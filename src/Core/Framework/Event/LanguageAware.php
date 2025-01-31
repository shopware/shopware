<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
#[IsFlowEventAware]
interface LanguageAware
{
    public const LANGUAGE_ID = 'languageId';

    public function getLanguageId(): ?string;
}
