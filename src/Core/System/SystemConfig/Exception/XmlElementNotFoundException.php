<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class XmlElementNotFoundException extends ShopwareHttpException
{
    public function __construct(string $element, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Unable to locate element with the name "{{ element }}".',
            ['element' => $element],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__XML_ELEMENT_NOT_FOUND';
    }
}
