<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;

interface EmailAware extends FlowEventAware
{
    public const EMAIL = 'email';

    public function getEmail(): string;
}
