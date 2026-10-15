<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Example\Operation;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class RequireExtensionsRequest extends AbstractRequest
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * @var array<string, mixed>
         */
        array $extensions,
    ) {
        $this->extensions = $extensions;
    }
}
