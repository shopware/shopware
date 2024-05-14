<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\PlatformRequest;

#[Package('core')]
class ContextTokenResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<string, mixed>
     */
    protected $object;

    public function __construct(
        string $token,
        ?string $redirectUrl = null
    ) {
        parent::__construct(new ArrayStruct([
            'redirectUrl' => $redirectUrl,
        ]));

        $this->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }

    public function getToken(): string
    {
        return $this->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
    }

    public function getRedirectUrl(): ?string
    {
        return $this->object->get('redirectUrl');
    }
}
