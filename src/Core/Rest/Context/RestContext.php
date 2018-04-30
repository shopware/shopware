<?php declare(strict_types=1);

namespace Shopware\Rest\Context;

use Shopware\Context\Struct\ApplicationContext;
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
     * @var ApplicationContext
     */
    private $applicationContext;

    /**
     * @var int
     */
    private $version;

    public function __construct(
        Request $request,
        ApplicationContext $applicationContext,
        ?string $userId
    ) {
        $this->request = $request;
        $this->applicationContext = $applicationContext;
        $this->userId = $userId;
        $this->version = (int) $this->getRequest()->get('version');
    }

    public function getApplicationContext(): ApplicationContext
    {
        return $this->applicationContext;
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
