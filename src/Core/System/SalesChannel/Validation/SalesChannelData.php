<?php

declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Validation;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelData
{
    public ?string $currentDefault = null;

    public ?string $newDefault = null;

    public ?string $updateId = null;

    /**
     * @var list<string>
     */
    public array $state = [];

    /**
     * @var list<string>|null
     */
    public ?array $inserts = null;

    /**
     * @var list<string>
     */
    public array $deletions = [];
}
