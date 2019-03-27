<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class UnsupportedModifierTypeException extends ShopwareHttpException
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $class;

    public function __construct(string $type, string $class)
    {
        $this->type = $type;
        $this->class = $class;

        parent::__construct(
            'Unsupported type "{{ type }}" in {{ class }}.',
            ['type' => $type, 'class' => $class]
        );
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__DISCOUNT_SURCHARGE_MODIFIER_TYPE_NOT_SUPPORTED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
