<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * @internal only for use by the app-system
 */
class UnallowedHostException extends \RuntimeException
{
    private array $allowedHosts;

    public function __construct(string $host, array $allowedHosts, ?\Throwable $previous = null)
    {
        $this->allowedHosts = $allowedHosts;

        parent::__construct(
            sprintf(
                'The host "%s" you tried to call is not listed in the allowed hosts of your manifest.',
                $host
            ),
            Response::HTTP_FORBIDDEN,
            $previous
        );
    }

    public function getAllowedHosts(): array
    {
        return $this->allowedHosts;
    }
}
