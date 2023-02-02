<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

/**
 * @internal
 */
class ScriptAppInformation
{
    private string $id;

    private string $name;

    private string $integrationId;

    public function __construct(string $id, string $name, string $integrationId)
    {
        $this->id = $id;
        $this->name = $name;
        $this->integrationId = $integrationId;
    }

    public function getAppId(): string
    {
        return $this->id;
    }

    public function getAppName(): string
    {
        return $this->name;
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }
}
