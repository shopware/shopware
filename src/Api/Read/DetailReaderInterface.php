<?php declare(strict_types=1);

namespace Shopware\Api\Read;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\Collection;

interface DetailReaderInterface
{
    /**
     * @param array              $uuids
     * @param TranslationContext $context
     *
     * @return Collection
     */
    public function readDetail(array $uuids, TranslationContext $context);
}
