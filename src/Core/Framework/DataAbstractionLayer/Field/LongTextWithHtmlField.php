<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;

/**
 * @deprecated tag:v6.3.0 - Use LongTextField with AllowHtml flag instead
 * @see AllowHtml
 * @see LongTextField
 */
class LongTextWithHtmlField extends LongTextField
{
    public function __construct(string $storageName, string $propertyName)
    {
        parent::__construct($storageName, $propertyName);
        $this->addFlags(new AllowHtml());
    }
}
