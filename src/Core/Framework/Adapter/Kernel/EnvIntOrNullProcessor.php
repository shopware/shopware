<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Kernel;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * @internal
 */
#[Package('framework')]
class EnvIntOrNullProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): mixed
    {
        $env = $getEnv($name);

        if ($env === null || $env === '') {
            return null;
        }

        return (int) $env;
    }

    public static function getProvidedTypes(): array
    {
        return [
            'int-or-null' => 'int',
        ];
    }
}
