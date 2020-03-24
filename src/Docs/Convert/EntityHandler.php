<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class EntityHandler
{
    /**
     * @var string
     */
    private $entityName;

    /**
     * @var Client
     */
    private $idMapperClient;

    /**
     * @var string
     */
    private $environment;

    public function __construct(string $entityName, Client $idMapperClient, string $environment)
    {
        $this->entityName = $entityName;
        $this->idMapperClient = $idMapperClient;
        $this->environment = $environment;
    }

    public function addEntryToMap(string $hash, int $entryId): void
    {
        $this->idMapperClient->post(
            sprintf('/%s/addEntry/%s', $this->entityName, $this->environment),
            [
                'form_params' => [
                    'hash' => $hash,
                    'mapped_id' => $entryId,
                ],
            ]
        );
    }

    public function getEntityForHash(string $hash): int
    {
        try {
            $response = $this->idMapperClient->get(sprintf('/%s/getEntry/%s/%s', $this->entityName, $hash, $this->environment));
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }

            return 0;
        }

        return (int) json_decode($response->getBody()->getContents(), true)['mapped_id'];
    }

    public function deleteEntityHash(string $hash): void
    {
        $this->idMapperClient->delete(sprintf('/%s/%s/%s', $this->entityName, $hash, $this->environment));
    }

    public function getAllEntityHashes(): array
    {
        $response = $this->idMapperClient->get(sprintf('/%s/getAll/%s', $this->entityName, $this->environment));

        return json_decode($response->getBody()->getContents(), true);
    }

    public function deleteById(int $id): void
    {
        $this->idMapperClient->delete(sprintf('/%s/deleteById/%d/%s', $this->entityName, $id, $this->environment));
    }
}
