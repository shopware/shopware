<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class InvalidCriteriaIdsException extends DataAbstractionLayerException
{
}
