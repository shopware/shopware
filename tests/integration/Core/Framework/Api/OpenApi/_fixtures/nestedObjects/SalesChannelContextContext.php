<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\AbstractDto;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Core context with general configuration values and state
 *
 * @codeCoverageIgnore
 */
final class SalesChannelContextContext extends AbstractDto
{
    public string $versionId;

    /**
     * @var list<string>
     */
    #[Assert\All(new Assert\Type('string'))]
    public array $languageIdChain;

    #[Assert\Valid]
    public SalesChannelContextContextSource $source;

    public bool $useCache;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
