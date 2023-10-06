<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class ConditionTypeNotFound extends \RuntimeException
{
}
