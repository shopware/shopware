<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class UnableToLoadPathException extends DataAbstractionLayerException
{
    /**
     * @param array<string, string> $paths
     */
    public function __construct(string $path, array $paths)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::UNABLE_TO_LOAD_PATH,
            'Unable to load %s: %s',
            ['path' => $path, 'paths' => print_r($paths, true)],
        );
    }
}
