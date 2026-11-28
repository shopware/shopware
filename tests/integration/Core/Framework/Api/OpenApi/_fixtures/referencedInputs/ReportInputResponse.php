<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Example\Operation;

use Shopware\Core\Example\Shared\InputSettings;
use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input report
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ReportInputResponse extends AbstractResponse
{
    #[Assert\Valid]
    public InputSettings $settings;

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
