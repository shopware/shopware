<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;

interface ContextTokenAware extends FlowEventAware
{
    public const CONTEXT_TOKEN = 'contextToken';

    public function getContextToken(): string;
}
