<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface ContentsAware extends FlowEventAware
{
    public const CONTENTS = 'contents';

    /**
     * @return array<string, mixed>
     */
    public function getContents(): array;
}
