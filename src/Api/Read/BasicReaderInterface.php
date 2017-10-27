<?php declare(strict_types=1);

namespace Shopware\Api\Read;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\Collection;

interface BasicReaderInterface
{
    /**
     * @param array              $uuids
     * @param TranslationContext $context
     *
     * @return Collection
     */
    public function readBasic(array $uuids, TranslationContext $context);
}
