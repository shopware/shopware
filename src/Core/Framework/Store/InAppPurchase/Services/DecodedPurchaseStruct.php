<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\InAppPurchase\Services;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

#[Package('checkout')]
final class DecodedPurchaseStruct
{
    #[NotNull, NotBlank, Type('string')]
    public readonly string $identifier;

    #[Type('string')]
    public readonly ?string $nextBookingDate;

    #[NotNull, Type('integer')]
    public readonly int $quantity;

    #[NotNull, NotBlank, Type('string')]
    public readonly string $sub;

    /**
     * @param array{identifier: string, nextBookingDate: string|null, quantity: int, sub: string} $data
     */
    public function __construct(array $data)
    {
        $this->identifier = $data['identifier'];
        $this->nextBookingDate = $data['nextBookingDate'];
        $this->quantity = $data['quantity'];
        $this->sub = $data['sub'];
    }

    protected function throwException(string $message): HttpException
    {
        return JWTException::invalidJwt($message);
    }
}
