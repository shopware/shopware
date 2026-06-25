<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Xml\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class MultiSelectField extends SingleSelectField
{
    public const COMPONENT_NAME = 'sw-multi-select';
}
