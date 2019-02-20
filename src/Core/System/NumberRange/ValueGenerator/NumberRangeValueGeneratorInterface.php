<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator;

use Shopware\Core\Framework\Context;

interface NumberRangeValueGeneratorInterface
{
    public function getValue(string $definition, Context $context, ?string $salesChannelId): string;
}
