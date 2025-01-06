<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class DecorationPatternException extends ShopwareHttpException
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $class;

    public function __construct(string $class)
    {
        parent::__construct(\sprintf(
            'The getDecorated() function of core class %s cannot be used. This class is the base class.',
            $class
        ));
    }

    public function getErrorCode(): string
    {
        return (string) Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
