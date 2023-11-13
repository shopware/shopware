<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('system-settings')]
class XmlElementNotFoundException extends ShopwareHttpException
{
    public function __construct(string $element)
    {
        parent::__construct(
            'Unable to locate element with the name "{{ element }}".',
            ['element' => $element]
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__XML_ELEMENT_NOT_FOUND';
    }
}
