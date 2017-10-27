<?php declare(strict_types=1);

namespace Shopware\Api\Write;

use Shopware\Context\Struct\TranslationContext;

interface WriterInterface
{
    /**
     * @param array              $data
     * @param TranslationContext $context
     *
     * @return WrittenEvent
     */
    public function update(array $data, TranslationContext $context);

    /**
     * @param array              $data
     * @param TranslationContext $context
     *
     * @return WrittenEvent
     */
    public function upsert(array $data, TranslationContext $context);

    /**
     * @param array              $data
     * @param TranslationContext $context
     *
     * @return \Shopware\Api\Write\WrittenEvent
     */
    public function create(array $data, TranslationContext $context);
}
