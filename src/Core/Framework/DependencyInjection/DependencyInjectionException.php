<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class DependencyInjectionException extends HttpException
{
    public const PROJECT_DIR_IS_NOT_A_STRING = 'FRAMEWORK__PROJECT_DIR_IS_NOT_A_STRING';
    public const BUNDLES_METADATA_IS_NOT_AN_ARRAY = 'FRAMEWORK__BUNDLES_METADATA_IS_NOT_AN_ARRAY';
    public const TAGGED_SERVICE_HAS_WRONG_TYPE = 'FRAMEWORK__TAGGED_SERVICE_HAS_WRONG_TYPE';
    public const PARAMETER_HAS_WRONG_TYPE = 'FRAMEWORK__PARAMETER_HAS_WRONG_TYPE';
    public const MISSING_ASSIGNABLE_DEFINITION = 'FRAMEWORK__MISSING_ASSIGNABLE_DEFINITION';
    public const ROOT_SOURCE_NAMESPACE_COLLISION = 'FRAMEWORK__ROOT_SOURCE_NAMESPACE_COLLISION';
    public const DATA_LOADER_RESERVED_SOURCE = 'FRAMEWORK__DATA_LOADER_RESERVED_SOURCE';
    public const DATA_LOADER_DUPLICATE_SOURCE = 'FRAMEWORK__DATA_LOADER_DUPLICATE_SOURCE';
    public const DATA_LOADER_CONFIG_KEY_DUPLICATE = 'FRAMEWORK__DATA_LOADER_CONFIG_KEY_DUPLICATE';
    public const DATA_LOADER_CONFIG_KEY_INVALID_TYPE = 'FRAMEWORK__DATA_LOADER_CONFIG_KEY_INVALID_TYPE';
    public const DATA_LOADER_CONFIG_KEY_UNKNOWN_TYPE = 'FRAMEWORK__DATA_LOADER_CONFIG_KEY_UNKNOWN_TYPE';
    public const DATA_LOADER_CONFIG_KEY_DEFAULT_MISMATCH = 'FRAMEWORK__DATA_LOADER_CONFIG_KEY_DEFAULT_MISMATCH';
    public const DATA_LOADER_RESERVED_CONFIG_KEY = 'FRAMEWORK__DATA_LOADER_RESERVED_CONFIG_KEY';
    public const DATA_LOADER_CONFIG_KEY_UNKNOWN_REFERENCED_TYPE = 'FRAMEWORK__DATA_LOADER_CONFIG_KEY_UNKNOWN_REFERENCED_TYPE';
    public const DATA_LOADER_CONFIG_KEY_REFERENCED_TYPE_MISPLACED = 'FRAMEWORK__DATA_LOADER_CONFIG_KEY_REFERENCED_TYPE_MISPLACED';
    public const DATA_LOADER_CONFIG_KEY_INVALID_MERGE = 'FRAMEWORK__DATA_LOADER_CONFIG_KEY_INVALID_MERGE';
    public const DATA_LOADER_SOURCE_WITHOUT_CONFIG_SERIALIZER = 'FRAMEWORK__DATA_LOADER_SOURCE_WITHOUT_CONFIG_SERIALIZER';
    public const DATA_LOADER_CLASS_IS_ABSTRACT = 'FRAMEWORK__DATA_LOADER_CLASS_IS_ABSTRACT';
    private const MCP_DUPLICATE_TOOL_NAME = 'FRAMEWORK__MCP_DUPLICATE_TOOL_NAME';
    private const MCP_UNKNOWN_TOOL_DEPENDENCY = 'FRAMEWORK__MCP_UNKNOWN_TOOL_DEPENDENCY';

    public static function projectDirNotInContainer(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PROJECT_DIR_IS_NOT_A_STRING,
            'Container parameter "kernel.project_dir" needs to be a string'
        );
    }

    public static function bundlesMetadataIsNotAnArray(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::BUNDLES_METADATA_IS_NOT_AN_ARRAY,
            'Container parameter "kernel.bundles_metadata" needs to be an array'
        );
    }

    public static function taggedServiceHasWrongType(string $service, string $tag, string $type): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TAGGED_SERVICE_HAS_WRONG_TYPE,
            \sprintf('Service "%s" is tagged as "%s" and must therefore be of type "%s".', $service, $tag, $type)
        );
    }

    public static function missingAssignableDefinition(string $service, string $tag): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_ASSIGNABLE_DEFINITION,
            \sprintf(
                'Service "%s" is tagged as "%s" but none of its constructor arguments reference an "%s" subclass.',
                $service,
                $tag,
                AbstractContentLayoutAssignableDefinition::class
            )
        );
    }

    public static function rootSourceNamespaceCollision(string $rootSource): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ROOT_SOURCE_NAMESPACE_COLLISION,
            \sprintf(
                'The content-layout entity type "%s" collides with a reserved root-source id (a section id or "none"). '
                . 'Entity-type ids, section ids, and "none" must remain disjoint so RootSourceRegistry resolves each to one source.',
                $rootSource
            )
        );
    }

    public static function dataLoaderReservedSource(string $loaderClass, string $source): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_RESERVED_SOURCE,
            \sprintf(
                'Data loader "%s" uses the reserved source name "%s". The names "loader" and "config" are reserved by the binding sugar grammar and cannot name a loader source.',
                $loaderClass,
                $source
            )
        );
    }

    public static function dataLoaderDuplicateSource(string $loaderClass, string $existingLoaderClass, string $source): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_DUPLICATE_SOURCE,
            \sprintf(
                'Data loader "%s" declares the source "%s", which data loader "%s" already declares. A source must resolve to exactly one loader; decorate the registered loader instead of registering a second one under the same source.',
                $loaderClass,
                $source,
                $existingLoaderClass
            )
        );
    }

    public static function dataLoaderConfigKeyDuplicate(string $loaderClass, string $key): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_CONFIG_KEY_DUPLICATE,
            \sprintf('Data loader "%s" declares the config key "%s" more than once in its configSpecification().', $loaderClass, $key)
        );
    }

    public static function dataLoaderConfigKeyInvalidType(string $loaderClass, string $key, string $kind, string $type): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_CONFIG_KEY_INVALID_TYPE,
            \sprintf('Config key "%s" of data loader "%s" has kind "%s", which requires type "string", got "%s".', $key, $loaderClass, $kind, $type)
        );
    }

    /**
     * @param list<string> $declarableTypes
     */
    public static function dataLoaderConfigKeyUnknownType(string $loaderClass, string $key, string $type, array $declarableTypes): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_CONFIG_KEY_UNKNOWN_TYPE,
            \sprintf(
                'Config key "%s" of data loader "%s" declares the unknown type "%s". Declarable types: "%s".',
                $key,
                $loaderClass,
                $type,
                implode('", "', $declarableTypes)
            )
        );
    }

    public static function dataLoaderConfigKeyDefaultMismatch(string $loaderClass, string $key, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_CONFIG_KEY_DEFAULT_MISMATCH,
            \sprintf('Config key "%s" of data loader "%s" has an incoherent default: %s.', $key, $loaderClass, $reason)
        );
    }

    /**
     * @param list<string> $referencedTypes
     */
    public static function dataLoaderConfigKeyUnknownReferencedType(string $loaderClass, string $key, string $referencedType, array $referencedTypes): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_CONFIG_KEY_UNKNOWN_REFERENCED_TYPE,
            \sprintf(
                'Config key "%s" of data loader "%s" declares the unknown referenced type "%s". Declarable referenced types: "%s".',
                $key,
                $loaderClass,
                $referencedType,
                implode('", "', $referencedTypes)
            )
        );
    }

    public static function dataLoaderConfigKeyReferencedTypeMisplaced(string $loaderClass, string $key, string $kind): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_CONFIG_KEY_REFERENCED_TYPE_MISPLACED,
            \sprintf(
                'Config key "%s" of data loader "%s" has kind "%s" and must therefore leave the referenced type at its "string" default: only a propertyReference key dereferences a stored value.',
                $key,
                $loaderClass,
                $kind
            )
        );
    }

    public static function dataLoaderConfigKeyInvalidMerge(string $loaderClass, string $key, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_CONFIG_KEY_INVALID_MERGE,
            \sprintf('Config key "%s" of data loader "%s" declares an invalid merge: %s.', $key, $loaderClass, $reason)
        );
    }

    public static function dataLoaderReservedConfigKey(string $loaderClass, string $key): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_RESERVED_CONFIG_KEY,
            \sprintf(
                'Data loader "%s" declares the reserved config key "%s". The names "loader" and "config" are reserved and cannot name a config key.',
                $loaderClass,
                $key
            )
        );
    }

    public static function dataLoaderSourceWithoutConfigSerializer(string $loaderClass, string $source): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_SOURCE_WITHOUT_CONFIG_SERIALIZER,
            \sprintf(
                'Data loader "%s" declares the source "%s", but no service tagged "content_system.config_serializer" returns "%s" from getSource(). Register the loader\'s config serializer under that tag.',
                $loaderClass,
                $source,
                $source
            )
        );
    }

    public static function dataLoaderClassIsAbstract(string $service, string $loaderClass): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_CLASS_IS_ABSTRACT,
            \sprintf(
                'Service "%s" is tagged as "content_system.data_loader" but its class "%s" is abstract. Tag a concrete loader: an abstract class cannot answer the introspection contract.',
                $service,
                $loaderClass
            )
        );
    }

    public static function parameterHasWrongType(string $parameter, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PARAMETER_HAS_WRONG_TYPE,
            \sprintf('Parameter "%s" should be: "%s". Got: "%s"', $parameter, $expectedType, $actualType)
        );
    }

    public static function unknownMcpToolDependency(string $dependentTool, string $missingDependency): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MCP_UNKNOWN_TOOL_DEPENDENCY,
            'MCP tool "{{ dependentTool }}" declares a dependency on "{{ missingDependency }}" which is not registered. Check the tool name or register the missing tool.',
            ['dependentTool' => $dependentTool, 'missingDependency' => $missingDependency],
        );
    }

    public static function duplicateMcpToolName(string $toolName, string $existingServiceId, string $newServiceId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MCP_DUPLICATE_TOOL_NAME,
            'Duplicate MCP tool name "{{ toolName }}": services "{{ existingServiceId }}" and "{{ newServiceId }}" conflict. Use a unique namespace prefix (e.g. "your-plugin-tool-name").',
            ['toolName' => $toolName, 'existingServiceId' => $existingServiceId, 'newServiceId' => $newServiceId],
        );
    }
}
