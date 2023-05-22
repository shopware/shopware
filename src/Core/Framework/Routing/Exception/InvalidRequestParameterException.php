<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class InvalidRequestParameterException extends RoutingException
{
    /**
     * @deprecated tag:v6.6.0 - public construct will be removed, use RoutingException::invalidRequestParameter instead
     */
    public function __construct(string $name)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_REQUEST_PARAMETER_CODE,
            'The parameter "{{ parameter }}" is invalid.',
            ['parameter' => $name]
        );
    }
}
