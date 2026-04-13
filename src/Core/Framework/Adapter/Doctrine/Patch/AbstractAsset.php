<?php declare(strict_types=1);

/**
 * Doctrine breaks all FK fields due namespacing. This reverts that feature
 * They don't want to include this fix: https://github.com/doctrine/dbal/pull/5132
 *
 * for the change we made @see AbstractAsset::_setName()
 */

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Exception\NotImplemented;
use Doctrine\DBAL\Schema\Name\GenericName;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\Deprecations\Deprecation;
use Shopware\Core\Framework\Log\Package;

if (class_exists('\\' . AbstractAsset::class, false)) {
    return;
}

/**
 * The abstract asset allows to reset the name of all assets without publishing this to the public userland.
 *
 * This encapsulation hack is necessary to keep a consistent state of the database schema. Say we have a list of tables
 * array($tableName => Table($tableName)); if you want to rename the table, you have to make sure
 *
 * @deprecated tag:v6.8.0 - Should not be needed with DBAL 5.0 anymore: https://github.com/doctrine/dbal/pull/7031
 */
#[Package('framework')]
abstract class AbstractAsset
{
    protected string $_name = '';

    /**
     * Indicates whether the object name has been initialized.
     */
    protected bool $isNameInitialized = false;

    /**
     * Namespace of the asset. If none isset the default namespace is assumed.
     *
     * @deprecated Use {@see NamedObject::getObjectName()} and {@see OptionallyQualifiedName::getQualifier()} instead.
     */
    protected ?string $_namespace = null;

    protected bool $_quoted = false;

    /**
     * @var list<Identifier>
     */
    private array $identifiers = [];

    private bool $validateFuture = false;

    public function __construct(?string $name = null)
    {
        if ($name === null) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6610',
                'Not passing $name to %s is deprecated.',
                __METHOD__,
            );

            return;
        }

        $this->_setName($name);
    }

    /**
     * Is this asset in the default namespace?
     *
     * @deprecated
     */
    public function isInDefaultNamespace(string $defaultNamespaceName): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6664',
            '%s is deprecated and will be removed in 5.0.',
            __METHOD__,
        );

        return $this->_namespace === $defaultNamespaceName || $this->_namespace === null;
    }

    /**
     * Gets the namespace name of this asset.
     *
     * If NULL is returned this means the default namespace is used.
     *
     * @deprecated Use {@see NamedObject::getObjectName()} and {@see OptionallyQualifiedName::getQualifier()} instead.
     */
    public function getNamespaceName(): ?string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6664',
            '%s is deprecated and will be removed in 5.0. Use NamedObject::getObjectName()'
            . ' and OptionallyQualifiedName::getQualifier() instead.',
            __METHOD__,
        );

        return $this->_namespace;
    }

    /**
     * The shortest name is stripped of the default namespace. All other
     * namespaced elements are returned as full-qualified names.
     *
     * @deprecated Use {@link getName()} instead.
     */
    public function getShortestName(?string $defaultNamespaceName): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6657',
            '%s is deprecated and will be removed in 5.0.',
            __METHOD__,
        );

        $shortestName = $this->getName();
        if ($this->_namespace === $defaultNamespaceName) {
            $shortestName = $this->_name;
        }

        return strtolower($shortestName);
    }

    /**
     * Checks if this asset's name is quoted.
     */
    public function isQuoted(): bool
    {
        return $this->_quoted;
    }

    /**
     * Returns the name of this schema asset.
     */
    public function getName(): string
    {
        if ($this->_namespace !== null) {
            return $this->_namespace . '.' . $this->_name;
        }

        return $this->_name;
    }

    /**
     * Gets the quoted representation of this asset but only if it was defined with one. Otherwise
     * return the plain unquoted value as inserted.
     *
     * @deprecated Use {@see NamedObject::getObjectName()} or {@see OptionallyQualifiedName::getObjectName()} followed
     * by {@see Name::toSQL()} instead.
     */
    public function getQuotedName(AbstractPlatform $platform): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6674',
            '%s is deprecated and will be removed in 5.0.',
            __METHOD__,
        );

        $keywords = $platform->getReservedKeywordsList();
        $folding = $platform->getUnquotedIdentifierFolding();
        $parts = $normalizedParts = [];

        foreach (explode('.', $this->getName()) as $identifier) {
            $isQuoted = $this->_quoted || $keywords->isKeyword($identifier);

            if (!$isQuoted) {
                $parts[] = $identifier;

                /** @phpstan-ignore argument.type */
                $normalizedParts[] = $folding->foldUnquotedIdentifier($identifier);
            } else {
                $parts[] = $platform->quoteSingleIdentifier($identifier);
                $normalizedParts[] = $identifier;
            }
        }

        $name = implode('.', $parts);

        if ($this->validateFuture) {
            $futureParts = array_map(static function (Identifier $identifier) use ($folding): string {
                $value = $identifier->getValue();

                if (!$identifier->isQuoted()) {
                    $value = $folding->foldUnquotedIdentifier($value);
                }

                return $value;
            }, $this->identifiers);

            if ($normalizedParts !== $futureParts) {
                Deprecation::trigger(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/6592',
                    'Relying on implicitly quoted identifiers preserving their original case is deprecated. '
                    . 'The current name %s will become %s in 5.0. '
                    . 'Please quote the name if the case needs to be preserved.',
                    $name,
                    implode('.', array_map([$platform, 'quoteSingleIdentifier'], $futureParts)),
                );
            }
        }

        return $name;
    }

    /**
     * Returns a parser for parsing the object name.
     *
     * @deprecated Parse the name in the constructor instead.
     *
     * @return Parser<N>
     */
    protected function getNameParser(): Parser
    {
        throw NotImplemented::fromMethod(static::class, __FUNCTION__);
    }

    /**
     * Sets the object name.
     *
     * @deprecated Set the name in the constructor instead.
     *
     * @param ?N $name
     */
    protected function setName(?Name $name): void
    {
        throw NotImplemented::fromMethod(static::class, __FUNCTION__);
    }

    /**
     * Sets the name of this asset.
     *
     * @deprecated Use the constructor instead.
     */
    protected function _setName(string $name): void
    {
        Deprecation::enableWithTriggerError();

        $this->isNameInitialized = false;

        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6610',
            '%s is deprecated. Use the constructor instead.',
            __METHOD__,
        );

        $input = $name;

        if ($this->isIdentifierQuoted($name)) {
            $this->_quoted = true;
            $name = $this->trimQuotes($name);
        }

        if (str_contains($name, '.')) {
            // SHOPWARE FIX -- Start
            // This is the fix, just ignore if the name has more then one point in it, original line:
            // $parts = explode('.', $name);
            $parts = explode('.', $name, 2);
            // SHOPWARE FIX -- End
            $this->_namespace = $parts[0];
            $name = $parts[1];
        }

        $this->_name = $name;

        $this->validateFuture = false;

        if ($input !== '') {
            try {
                $parsedName = $this->getNameParser()->parse($input);
            } catch (\Throwable $e) {
                // Mute as this will always happen with SHOPWARE current foreign keys, as they are not compatible
                // with this parser, since they are not strict (e.g. `fk.shopware.order_address`).
                /*
                Deprecation::trigger(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/6592',
                    'Unable to parse object name: %s.',
                    $e->getMessage(),
                );
                */

                return;
            }
        } else {
            $parsedName = null;
        }

        try {
            $this->setName($parsedName);
        } catch (\Throwable $e) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6646',
                'Using invalid database object names is deprecated: %s.',
                $e->getMessage(),
            );

            return;
        }

        $this->isNameInitialized = true;

        if ($parsedName === null) {
            $this->identifiers = [];

            return;
        }

        if ($parsedName instanceof UnqualifiedName) {
            $identifiers = [$parsedName->getIdentifier()];
        } elseif ($parsedName instanceof OptionallyQualifiedName) {
            $unqualifiedName = $parsedName->getUnqualifiedName();
            $qualifier = $parsedName->getQualifier();

            $identifiers = $qualifier !== null
                ? [$qualifier, $unqualifiedName]
                : [$unqualifiedName];
        } elseif ($parsedName instanceof GenericName) {
            $identifiers = $parsedName->getIdentifiers();
        } else {
            return;
        }

        switch (\count($identifiers)) {
            case 1:
                $namespace = null;
                $name = $identifiers[0];
                break;

            case 2:
                [$namespace, $name] = $identifiers;
                break;

            default:
                return;
        }

        $this->identifiers = $identifiers;
        $this->validateFuture = true;

        // Mute as it's the format expected in 5.0, not really a deprecation then,
        // as stated in https://github.com/doctrine/dbal/issues/7030
        /*
        $futureName = $name->getValue();
        $futureNamespace = $namespace?->getValue();

        if ($this->_name !== $futureName) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6592',
                'Instead of "%s", this name will be interpreted as "%s" in 5.0',
                $this->_name,
                $futureName,
            );
        }

        if ($this->_namespace === $futureNamespace) {
            return;
        }
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6592',
            'Instead of %s, the namespace in this name will be interpreted as %s in 5.0.',
            $this->_namespace !== null ? \sprintf('"%s"', $this->_namespace) : 'null',
            $futureNamespace !== null ? \sprintf('"%s"', $futureNamespace) : 'null',
        );
        */
    }

    /**
     * Checks if this identifier is quoted.
     *
     * @deprecated Parse the name and introspect its identifiers individually using {@see Identifier::isQuoted()}
     *             instead.
     */
    protected function isIdentifierQuoted(string $identifier): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6677',
            '%s is deprecated and will be removed in 5.0.',
            __METHOD__,
        );

        return isset($identifier[0]) && ($identifier[0] === '`' || $identifier[0] === '"' || $identifier[0] === '[');
    }

    /**
     * Trim quotes from the identifier.
     */
    protected function trimQuotes(string $identifier): string
    {
        return str_replace(['`', '"', '[', ']'], '', $identifier);
    }

    /**
     * Generates an identifier from a list of column names obeying a certain string length.
     *
     * This is especially important for Oracle, since it does not allow identifiers larger than 30 chars,
     * however building idents automatically for foreign keys, composite keys or such can easily create
     * very long names.
     *
     * @param array<int, string> $columnNames
     * @param positive-int $maxSize
     *
     * @return non-empty-string
     */
    protected function _generateIdentifierName(array $columnNames, string $prefix = '', int $maxSize = 30): string
    {
        $hash = \implode('', \array_map(static fn ($column) => \dechex(\crc32($column)), $columnNames));

        return strtoupper(substr($prefix . '_' . $hash, 0, $maxSize));
    }
}
