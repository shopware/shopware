<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Matching payload
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class QueryPayloadResponse extends AbstractResponse
{
    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public bool $matched,
    ) {
        parent::__construct();
    }
}
