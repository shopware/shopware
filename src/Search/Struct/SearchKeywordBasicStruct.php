<?php declare(strict_types=1);

namespace Shopware\Search\Struct;

use Shopware\Api\Entity\Entity;

class SearchKeywordBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $keyword;

    /**
     * @var string
     */
    protected $shopUuid;

    /**
     * @var int
     */
    protected $documentCount;

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): void
    {
        $this->keyword = $keyword;
    }

    public function getShopUuid(): string
    {
        return $this->shopUuid;
    }

    public function setShopUuid(string $shopUuid): void
    {
        $this->shopUuid = $shopUuid;
    }

    public function getDocumentCount(): int
    {
        return $this->documentCount;
    }

    public function setDocumentCount(int $documentCount): void
    {
        $this->documentCount = $documentCount;
    }
}
