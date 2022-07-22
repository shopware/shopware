<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Struct\ArrayStruct;

class ContextTokenResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<string, mixed>
     */
    protected $object;

    public function __construct(string $token, ?string $redirectUrl = null)
    {
        parent::__construct(
            new ArrayStruct(
                [
                    'contextToken' => $token,
                    'redirectUrl' => $redirectUrl,
                ]
            )
        );
    }

    public function getToken(): string
    {
        return $this->object->get('contextToken');
    }

    public function getRedirectUrl(): ?string
    {
        return $this->object->get('redirectUrl');
    }
}
