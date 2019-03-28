<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MissingRequestParameterException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $path;

    public function __construct(string $name, string $path = '')
    {
        $this->name = $name;
        $this->path = $path;

        parent::__construct('Parameter "{{ parameterName }}" is missing.', ['parameterName' => $name]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__MISSING_REQUEST_PARAMETER';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
