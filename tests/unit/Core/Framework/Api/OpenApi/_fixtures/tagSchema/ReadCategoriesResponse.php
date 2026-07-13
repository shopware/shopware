<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Category list
 */
final readonly class ReadCategoriesResponse
{
    public function __construct(
        /**
         * @var list<Category>
         */
        #[Assert\Valid]
        public ?array $elements = null,
    ) {
    }
}
