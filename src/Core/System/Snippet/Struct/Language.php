<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
class Language extends Struct
{
    public function __construct(
        public string $locale,
        public string $name,
    ) {
    }
}
