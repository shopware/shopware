<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * @internal only for use by the app-system
 *
 * @package core
 */
class UnallowedHostException extends \RuntimeException
{
    public function __construct(string $host, private readonly array $allowedHosts, string $appName, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'The host "%s" you tried to call is not listed in the allowed hosts in the manifest file for app "%s".',
                $host,
                $appName
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
