<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer\fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\PlatformRequest;

abstract class SerializationFixture
{
    public const API_BASE_URL = 'http://localhost/api/v' . PlatformRequest::API_VERSION;
    public const SALES_CHANNEL_API_BASE_URL = 'http://localhost/sales-channel-api/v' . PlatformRequest::API_VERSION;
    public const API_VERSION = 1;

    /**
     * @return EntityCollection|Entity
     */
    abstract public function getInput();

    public function getAdminJsonApiFixtures(): array
    {
        $fixtures = $this->getJsonApiFixtures(self::API_BASE_URL);

        return $this->removeProtectedAdminJsonApiData($fixtures);
    }

    public function getSalesChannelJsonApiFixtures(): array
    {
        $fixtures = $this->getJsonApiFixtures(self::SALES_CHANNEL_API_BASE_URL);

        return $this->removeProtectedSalesChannelJsonApiData($fixtures);
    }

    public function getAdminJsonFixtures(): array
    {
        $fixtures = $this->getJsonFixtures();

        return $this->removeProtectedAdminJsonData($fixtures);
    }

    public function getSalesChannelJsonFixtures(): array
    {
        $fixtures = $this->getJsonFixtures();

        return $this->removeProtectedSalesChannelJsonData($fixtures);
    }

    abstract protected function getJsonApiFixtures(string $baseUrl): array;

    abstract protected function getJsonFixtures(): array;

    protected function removeProtectedSalesChannelJsonApiData(array $fixtures): array
    {
        return $fixtures;
    }

    protected function removeProtectedAdminJsonApiData(array $fixtures): array
    {
        return $fixtures;
    }

    protected function removeProtectedSalesChannelJsonData(array $fixtures): array
    {
        return $fixtures;
    }

    protected function removeProtectedAdminJsonData(array $fixtures): array
    {
        return $fixtures;
    }
}
