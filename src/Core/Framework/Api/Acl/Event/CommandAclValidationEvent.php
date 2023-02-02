<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Event;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Symfony\Contracts\EventDispatcher\Event;

class CommandAclValidationEvent extends Event
{
    /**
     * @var array
     */
    private $missingPrivileges;

    /**
     * @var AdminApiSource
     */
    private $source;

    /**
     * @var WriteCommand
     */
    private $command;

    public function __construct(array $missingPrivileges, AdminApiSource $source, WriteCommand $command)
    {
        $this->missingPrivileges = $missingPrivileges;
        $this->source = $source;
        $this->command = $command;
    }

    public function getMissingPrivileges(): array
    {
        return $this->missingPrivileges;
    }

    public function addMissingPrivilege(string $privilege): void
    {
        $this->missingPrivileges[] = $privilege;
    }

    public function getSource(): AdminApiSource
    {
        return $this->source;
    }

    public function getCommand(): WriteCommand
    {
        return $this->command;
    }
}
