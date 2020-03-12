<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

class CredentialsStruct
{
    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $sbpServerUrl;

    /**
     * @var int
     */
    private $rootCategoryId;

    /**
     * @var string
     */
    private $idMapperUrl;

    public function __construct(string $token, string $sbpServerUrl, int $rootCategoryId, string $idMapperUrl)
    {
        $this->token = $token;
        $this->sbpServerUrl = $sbpServerUrl;
        $this->rootCategoryId = $rootCategoryId;
        $this->idMapperUrl = $idMapperUrl;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getSbpServerUrl(): string
    {
        return $this->sbpServerUrl;
    }

    public function getRootCategoryId(): int
    {
        return $this->rootCategoryId;
    }

    public function getIdMapperUrl(): string
    {
        return $this->idMapperUrl;
    }
}
