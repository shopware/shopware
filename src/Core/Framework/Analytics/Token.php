<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Analytics;

use Shopware\Core\Framework\Log\Package;

#[Package('data-services')]
readonly class Token implements \JsonSerializable
{
    public function __construct(public string $token, public \DateTimeImmutable $expiresAt)
    {
    }

    /**
     * @return array{token: string, expiresAt: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'token' => $this->token,
            'expiresAt' => $this->expiresAt->getTimestamp(),
        ];
    }
}
