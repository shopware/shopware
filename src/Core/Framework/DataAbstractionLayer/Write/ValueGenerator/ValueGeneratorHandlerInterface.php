<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator;

use Shopware\Core\Framework\Context;

interface ValueGeneratorHandlerInterface
{
    public function getValue(): string;

    public function setContext(Context $context): void;
}
