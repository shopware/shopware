<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @extends StoreApiResponse<ArrayStruct<array{token: string, expiresAt: string}>>
 */
#[Package('framework')]
class ContextHandoffTokenResponse extends StoreApiResponse
{
    public function __construct(
        string $handoffToken,
        \DateTimeImmutable $expiresAt
    ) {
        parent::__construct(new ArrayStruct([
            'token' => $handoffToken,
            'expiresAt' => $expiresAt->format(\DateTimeInterface::RFC3339),
        ]));
    }

    public function getHandoffToken(): string
    {
        return $this->object->all()['token'];
    }

    public function getExpiresAt(): string
    {
        return $this->object->all()['expiresAt'];
    }
}
