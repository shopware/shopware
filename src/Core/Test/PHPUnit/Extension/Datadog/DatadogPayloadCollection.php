<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\Datadog;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<DatadogPayload>
 */
#[Package('core')]
class DatadogPayloadCollection extends Collection
{
}
