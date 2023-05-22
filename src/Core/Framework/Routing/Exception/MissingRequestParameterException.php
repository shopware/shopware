<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class MissingRequestParameterException extends RoutingException
{
    /**
     * @deprecated tag:v6.6.0 - public construct will be removed, use RoutingException::missingRequestParameter instead
     */
    public function __construct(
        private readonly string $name,
        private readonly string $path = ''
    ) {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_REQUEST_PARAMETER_CODE,
            'Parameter "{{ parameterName }}" is missing.',
            ['parameterName' => $name]
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
