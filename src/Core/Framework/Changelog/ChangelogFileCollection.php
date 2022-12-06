<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @package core
 *
 * @internal
 *
 * @extends Collection<ChangelogFile>
 */
class ChangelogFileCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return ChangelogFile::class;
    }
}
