<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlIndexingMessage extends EntityIndexingMessage
{
    /**
     * @var array<string, list<string>>
     */
    private array $idsByEntity = [];

    /**
     * @param array<string, list<string>> $idsByEntity
     */
    public function setIdsByEntity(array $idsByEntity): void
    {
        $this->idsByEntity = $idsByEntity;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getIdsByEntity(): array
    {
        return $this->idsByEntity;
    }

    /**
     * @return list<string>
     */
    public function getIds(string $entityName): array
    {
        return $this->idsByEntity[$entityName] ?? [];
    }
}
