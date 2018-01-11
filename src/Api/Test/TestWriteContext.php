<?php declare(strict_types=1);

namespace Shopware\Api\Test;

use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Context\Struct\TranslationContext;

class TestWriteContext extends WriteContext
{
    private function __construct()
    {
    }

    public static function create(): WriteContext
    {
        return WriteContext::createFromTranslationContext(
            TranslationContext::createDefaultContext()
        );
    }
}
