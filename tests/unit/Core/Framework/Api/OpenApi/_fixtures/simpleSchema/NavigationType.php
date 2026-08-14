<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\AbstractDto;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Navigation entry type
 *
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class NavigationType extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Type of the navigation entry
         */
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['page', 'link', 'folder'])]
        public string $type,
        /**
         * Route name for the navigation
         */
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['frontend.navigation.page', 'frontend.landing.page', 'frontend.detail.page'])]
        public string $routeName,
        /**
         * Type of the link if type is link
         */
        #[Assert\Choice(choices: ['external', 'category', 'product', 'landing_page'])]
        public ?string $linkType = null,
    ) {
    }
}
