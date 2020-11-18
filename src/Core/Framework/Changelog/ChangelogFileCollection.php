<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method ChangelogFile[]    getIterator()
 * @method ChangelogFile[]    getElements()
 * @method ChangelogFile|null first()
 * @method ChangelogFile|null last()
 */
class ChangelogFileCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return ChangelogFile::class;
    }
}
