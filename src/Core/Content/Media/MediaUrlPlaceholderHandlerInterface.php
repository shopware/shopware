<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
interface MediaUrlPlaceholderHandlerInterface
{
    public function replace(string $content): string;
}
