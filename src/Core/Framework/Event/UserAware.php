<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

interface UserAware extends FlowEventAware
{
    public function getUserId(): string;
}
