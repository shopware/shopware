<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Struct\ArrayStruct;

class ContextTokenResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct
     */
    protected $object;

    public function __construct(string $token)
    {
        parent::__construct(new ArrayStruct(['contextToken' => $token]));
    }

    public function getToken(): string
    {
        return $this->object->get('contextToken');
    }
}
