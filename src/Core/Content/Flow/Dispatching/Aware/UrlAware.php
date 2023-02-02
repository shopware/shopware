<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;

interface UrlAware extends FlowEventAware
{
    public const URL = 'url';

    public function getUrl(): string;
}
