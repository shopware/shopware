<?php

namespace Shopware\Framework\Read;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\AbstractWrittenEvent;

interface WriterInterface
{
    /**
     * @param array $data
     * @param TranslationContext $context
     * @return AbstractWrittenEvent
     */
    public function update(array $data, TranslationContext $context);

    /**
     * @param array $data
     * @param TranslationContext $context
     * @return AbstractWrittenEvent
     */
    public function upsert(array $data, TranslationContext $context);

    /**
     * @param array $data
     * @param TranslationContext $context
     * @return AbstractWrittenEvent
     */
    public function create(array $data, TranslationContext $context);
}