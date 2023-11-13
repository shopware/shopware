<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<ChangelogFile>
 */
#[Package('core')]
class ChangelogFileCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return ChangelogFile::class;
    }
}
