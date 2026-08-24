<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;

/**
 * Return values:
 * - ContentDataLoaderResult::notFound() → No data, page cacheable
 * - ContentDataLoaderResult::cached($data, ...$tags) → Data with cache tags
 * - ContentDataLoaderResult::cachedExternally($data) → Data, tags handled elsewhere
 *
 * @template TData of Struct
 */
#[Package('framework')]
abstract class AbstractContentDataLoader
{
    /**
     * Used by the DI ServiceLocator for indexing.
     */
    abstract public static function getRequirementType(): string;

    /**
     * The types this loader can produce, each with the config seed needed to produce it.
     *
     * Default: one capability derived from the `@extends AbstractContentDataLoader<T>` PHPDoc,
     * carrying the produced class and its generic parameters, with an empty config template.
     * Wildcard loaders (entity, entity_collection) override this to enumerate the live definition registry.
     *
     * @return list<LoaderTypeCapability>
     */
    public function producibleTypes(): array
    {
        return [static::extendsDescriptor()];
    }

    /**
     * The concrete type a specific config produces.
     *
     * Default: the single `@extends` produced class, ignoring config.
     * Wildcard loaders override this to map the config to a registered entity class.
     */
    public function resolveProducedType(AbstractContentDataLoaderConfig $config): string
    {
        return static::extendsDescriptor()->producedType;
    }

    /**
     * The declared config contract of this loader: the kind, type, and default of each config key.
     *
     * Default: an empty specification, legitimate for config-less loaders. Every loader whose config
     * serializer accepts keys overrides this to declare them. This is the same override surface as
     * producibleTypes(): the specification is metadata about the config, while the config serializer
     * stays the decoding authority.
     */
    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([]);
    }

    /**
     * Resolves the single type capability declared via the `@extends AbstractContentDataLoader<T>` PHPDoc.
     *
     * 1. phpstan/phpdoc-parser extracts the `@extends` tag and provides the type AST
     * 2. symfony/type-info's TypeContext resolves short class names to FQCNs
     *
     * Dry-run by ContentSystemDataLoaderCompilerPass at container build time so a missing or
     * unresolvable `@extends` annotation fails the build.
     *
     * Must stay public static: the compiler pass invokes it as `$class::extendsDescriptor()` on a bare
     * class-string with no loader instance, and both `producibleTypes()` and `resolveProducedType()` reuse it.
     */
    public static function extendsDescriptor(): LoaderTypeCapability
    {
        $reflection = new \ReflectionClass(static::class);
        $docComment = $reflection->getDocComment();

        if ($docComment === false) {
            throw ContentSystemException::missingExtendsAnnotation(static::class);
        }

        $config = new ParserConfig([]);
        $lexer = new Lexer($config);
        $parser = new PhpDocParser(
            $config,
            new TypeParser($config, $constExprParser = new ConstExprParser($config)),
            $constExprParser,
        );

        $docNode = $parser->parse(new TokenIterator($lexer->tokenize($docComment)));
        $extendsValues = $docNode->getExtendsTagValues();

        if ($extendsValues === []) {
            throw ContentSystemException::missingExtendsAnnotation(static::class);
        }

        $typeNode = $extendsValues[0]->type;
        \assert($typeNode->genericTypes !== [], 'ExtendsTagValueNode guarantees a GenericTypeNode with at least one type parameter');
        $dataTypeNode = $typeNode->genericTypes[0];

        $contextFactory = new TypeContextFactory();
        $context = $contextFactory->createFromClassName(static::class);

        if ($dataTypeNode instanceof GenericTypeNode) {
            $className = $context->normalize((string) $dataTypeNode->type);

            if (!is_a($className, Struct::class, true)) {
                throw ContentSystemException::unresolvableTypeClass($className, static::class);
            }

            $genericParameters = [];
            foreach ($dataTypeNode->genericTypes as $param) {
                $resolved = $context->normalize((string) $param);

                if (!is_a($resolved, Struct::class, true)) {
                    throw ContentSystemException::unresolvableTypeClass($resolved, static::class);
                }

                $genericParameters[] = $resolved;
            }

            return new LoaderTypeCapability($className, [], $genericParameters);
        }

        if ($dataTypeNode instanceof IdentifierTypeNode) {
            $className = $context->normalize($dataTypeNode->name);

            if (!is_a($className, Struct::class, true)) {
                throw ContentSystemException::unresolvableTypeClass($className, static::class);
            }

            return new LoaderTypeCapability($className);
        }

        throw ContentSystemException::unsupportedTypeNode($dataTypeNode::class);
    }

    abstract public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult;
}
