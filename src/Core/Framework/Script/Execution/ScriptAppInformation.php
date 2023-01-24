<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

/**
 * @internal
 *
 * @package core
 */
class ScriptAppInformation
{
    public function __construct(private readonly string $id, private readonly string $name, private readonly string $integrationId)
    {
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
