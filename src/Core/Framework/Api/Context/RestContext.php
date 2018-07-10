<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

class RestContext
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var int
     */
    private $version;

    public function __construct(
        Request $request,
        Context $context
    ) {
        $this->request = $request;
        $this->context = $context;
        $this->version = (int) $this->getRequest()->get('version');
    }

    public function getContext(): Context
    {
        return $this->context;
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
