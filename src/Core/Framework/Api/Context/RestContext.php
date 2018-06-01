<?php declare(strict_types=1);

namespace Shopware\Framework\Api\Context;

use Shopware\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

class RestContext
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var string
     */
    private $userId;

    /**
     * @var \Shopware\Framework\Context
     */
    private $context;

    /**
     * @var int
     */
    private $version;

    public function __construct(
        Request $request,
        Context $context,
        ?string $userId
    ) {
        $this->request = $request;
        $this->context = $context;
        $this->userId = $userId;
        $this->version = (int) $this->getRequest()->get('version');
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
