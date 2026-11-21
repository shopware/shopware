<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

final class ReadNewsletterRecipientResponse extends AbstractResponse
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * @var array<string, mixed>
         *
         * @todo Replace with the generated DTO once static schema files exist for generic entity definitions.
         */
        #[Assert\NotNull]
        public array $status,
        #[Assert\NotNull]
        #[Assert\Choice(choices: [0.5, 10.5, 20.5])]
        public float $priority,
    ) {
        parent::__construct(statusCode: Response::HTTP_CREATED);
    }
}
