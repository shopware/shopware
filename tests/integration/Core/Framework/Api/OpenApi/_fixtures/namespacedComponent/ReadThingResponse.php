<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Example\Operation;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Success
 *
 * @codeCoverageIgnore
 */
final class ReadThingResponse extends AbstractResponse
{
    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public string $id,
    ) {
        parent::__construct();
    }
}
