<?php

declare(strict_types=1);

/**
 * Doctrine breaks all FK fields due namespacing with dots in the name.
 * Patch can be removed once DBAL v5 is required.
 *
 * Have a look at {@see UnqualifiedNameParser::applyShopwarePatch()} for the patch.
 *
 * Namespace must match upstream to override Doctrine's class via autoload.files (excluded from classmap in composer.json)
 */

namespace Doctrine\DBAL\Schema\Name\Parser;

use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parser\Exception\InvalidName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Shopware\Core\Framework\Log\Package;

if (class_exists('\\' . UnqualifiedNameParser::class, false)) {
    return;
}

/**
 * @internal
 *
 * @implements Parser<UnqualifiedName>
 */
#[Package('framework')]
final readonly class UnqualifiedNameParser implements Parser
{
    public function __construct(private GenericNameParser $genericNameParser)
    {
    }

    public function parse(string $input): UnqualifiedName
    {
        $identifiers = $this->genericNameParser->parse($input)
            ->getIdentifiers();

        $identifiers = $this->applyShopwarePatch($identifiers);

        if (\count($identifiers) > 1) {
            throw InvalidName::forUnqualifiedName(\count($identifiers));
        }

        return new UnqualifiedName($identifiers[0]);
    }

    /**
     * Foreign keys and indexes in Shopware have a naming convention with dots.
     * DBAL treats the dots as separator for namespaces and splits up the name by the dots {@see AbstractAsset::_setName()},
     * causing issues as names are then duplicated. The {@see GenericNameParser::parse()} does the same,
     * so this patch merges the identifiers to a single identifier to be compliant with DBAL again.
     * Once DBAL v5 is required, this patch can be removed as the name handling will be fixed.
     *
     * @param non-empty-list<Identifier> $identifiers
     *
     * @return non-empty-list<Identifier>
     */
    private function applyShopwarePatch(array $identifiers): array
    {
        if (\count($identifiers) === 1) {
            return $identifiers;
        }

        /**
         * Only fix the identifier if it starts with a prefix defined by Shopware convention
         */
        if (!\in_array($identifiers[0]->getValue(), ['fk', 'uniq', 'idx', 'uidx'], true)) {
            return $identifiers;
        }

        $identifierValues = array_map(static fn (Identifier $identifier): string => $identifier->getValue(), $identifiers);
        $joinedIdentifier = implode('.', $identifierValues);

        return [Identifier::unquoted($joinedIdentifier)];
    }
}
