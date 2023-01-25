<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<MissingSnippetStruct>
 */
#[Package('system-settings')]
class MissingSnippetCollection extends Collection
{
}
