<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;

#[Package('framework')]
class ContextTokenStruct extends Struct
{
    public function __construct(protected string $token)
    {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        unset($data['token']);

        $data[PlatformRequest::HEADER_CONTEXT_TOKEN] = $this->getToken();

        return $data;
    }

    public function getApiAlias(): string
    {
        return 'context_token';
    }
}
