<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Filter;

class HtmlFilter implements Filter
{
    public function filter($value)
    {
        return strip_tags((string) $value);
    }
}
