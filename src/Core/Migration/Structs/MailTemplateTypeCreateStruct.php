<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Structs;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateTypeCreateStruct
{
    /**
     * Example for available entities:
     *
     * $availableEntities = [
     *      'order' => 'order',
     *      'previousState' => 'state_machine_state',
     *      'newState' => 'state_machine_state',
     *      'salesChannel' => 'sales_channel',
     * ]
     *
     * @param array<string, string> $availableEntities
     */
    public function __construct(
        protected string $technicalName,
        protected string $enName,
        protected string $deName,
        protected array $availableEntities = [],
    ) {
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getEnName(): string
    {
        return $this->enName;
    }

    public function getDeName(): string
    {
        return $this->deName;
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableEntities(): array
    {
        return $this->availableEntities;
    }
}
