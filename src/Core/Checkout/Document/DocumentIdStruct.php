<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Struct\Struct;

class DocumentIdStruct extends Struct
{
    /**
     * @vars string
     */
    protected $id;

    /**
     * @var string
     */
    protected $deepLinkCode;

    public function __construct(string $id, string $deepLinkCode)
    {
        $this->id = $id;
        $this->deepLinkCode = $deepLinkCode;
    }

    public function getDeepLinkCode(): string
    {
        return $this->deepLinkCode;
    }

    public function setDeepLinkCode(string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }
}
