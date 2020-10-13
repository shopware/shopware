<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class AclGetAdditionalPrivilegesEvent extends NestedEvent
{
    /**
     * @var array
     */
    private $privileges;

    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context, array $privileges)
    {
        $this->privileges = $privileges;
        $this->context = $context;
    }

    public function getPrivileges(): array
    {
        return $this->privileges;
    }

    public function setPrivileges(array $privileges): void
    {
        $this->privileges = $privileges;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
