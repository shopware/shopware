<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * Defines a human-readable description for an association field.
 * This description is used in OpenAPI documentation to help developers
 * understand what the association represents and when to use it.
 *
 * Example:
 * ```php
 * (new ManyToOneAssociationField('cover', 'product_media_id', ProductMediaDefinition::class))
 *     ->addFlags(
 *         new ApiAware(),
 *         new AssociationDescription('Main product image displayed in listings and detail pages')
 *     );
 * ```
 */
#[Package('framework')]
final class AssociationDescription extends Flag
{
    public function __construct(
        private readonly string $description
    ) {
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function parse(): \Generator
    {
        yield 'association_description' => $this->description;
    }
}
