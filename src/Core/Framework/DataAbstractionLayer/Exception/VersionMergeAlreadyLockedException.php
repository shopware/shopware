<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class VersionMergeAlreadyLockedException extends ShopwareHttpException
{
    public function __construct(string $versionId)
    {
        parent::__construct(
            'Merging of version {{ versionId }} is locked, as the merge is already running by another process.',
            ['versionId' => $versionId]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__VERSION_MERGE_ALREADY_LOCKED';
    }
}
