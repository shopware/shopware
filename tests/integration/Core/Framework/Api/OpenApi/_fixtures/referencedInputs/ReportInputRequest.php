<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Example\Operation;

use Shopware\Core\Example\Shared\InputSettings;
use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ReportInputRequest extends AbstractRequest
{
    #[Assert\Valid]
    public InputSettings $settings;

    public int $limit;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public string $bodyValue,
        #[Assert\NotNull]
        public string $itemId,
        #[Assert\NotNull]
        public string $locale,
    ) {
    }
}
