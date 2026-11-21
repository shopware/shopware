<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Example\FieldPresence;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Shared and response-only field presence
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class CheckSharedFieldPresenceResponse extends AbstractResponse
{
    #[Assert\Valid]
    public SharedFieldPresence $shared;

    #[Assert\Valid]
    public ResponseOnlyFieldPresence $responseOnly;

    /**
     * @internal
     */
    public function __construct(
    ) {
        parent::__construct();
    }
}
