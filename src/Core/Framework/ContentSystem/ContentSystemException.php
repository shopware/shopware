<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\Field\StoredElementListFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndexFactory;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @final
 */
#[Package('framework')]
class ContentSystemException extends HttpException
{
    public const LAYOUT_ASSIGNMENT_NOT_FOUND = 'CONTENT_SYSTEM__LAYOUT_ASSIGNMENT_NOT_FOUND';
    public const LAYOUT_NOT_FOUND = 'CONTENT_SYSTEM__LAYOUT_NOT_FOUND';
    public const INVALID_MAP_KEY = 'CONTENT_SYSTEM__INVALID_MAP_KEY';
    public const INVALID_MAP_VALUE = 'CONTENT_SYSTEM__INVALID_MAP_VALUE';
    public const DATA_LOADER_NOT_REGISTERED = 'CONTENT_SYSTEM__DATA_LOADER_NOT_REGISTERED';
    public const CONFIG_SERIALIZER_NOT_REGISTERED = 'CONTENT_SYSTEM__CONFIG_SERIALIZER_NOT_REGISTERED';
    public const INVALID_FIELD_TYPE = 'CONTENT_SYSTEM__INVALID_FIELD_TYPE';
    public const INVALID_FIELD_VALUE_TYPE = 'CONTENT_SYSTEM__INVALID_FIELD_VALUE_TYPE';
    public const INVALID_FIELD_VALUE_RANGE = 'CONTENT_SYSTEM__INVALID_FIELD_VALUE_RANGE';
    public const ELEMENT_NOT_FOUND = 'CONTENT_SYSTEM__ELEMENT_NOT_FOUND';
    public const DUPLICATE_ELEMENT_ID = 'CONTENT_SYSTEM__DUPLICATE_ELEMENT_ID';
    public const CONTEXT_DELIVERY_MISSING = 'CONTENT_SYSTEM__CONTEXT_DELIVERY_MISSING';
    public const NO_FACTORY_CAN_HANDLE = 'CONTENT_SYSTEM__NO_FACTORY_CAN_HANDLE';
    public const INVALID_ENTITY_PATH = 'CONTENT_SYSTEM__INVALID_ENTITY_PATH';
    public const CONTEXT_PATH_NOT_RESOLVABLE = 'CONTENT_SYSTEM__CONTEXT_PATH_NOT_RESOLVABLE';
    public const REDISTRIBUTE_DOTTED_PATH = 'CONTENT_SYSTEM__REDISTRIBUTE_DOTTED_PATH';
    public const REDISTRIBUTE_CONFLICT = 'CONTENT_SYSTEM__REDISTRIBUTE_CONFLICT';
    public const CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE = 'CONTENT_SYSTEM__CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE';
    public const PROPERTY_ALIAS_WITH_DOT_NOTATION = 'CONTENT_SYSTEM__PROPERTY_ALIAS_WITH_DOT_NOTATION';
    public const PROPERTY_ALIAS_COLLISION = 'CONTENT_SYSTEM__PROPERTY_ALIAS_COLLISION';
    public const PROVIDER_DELIVERY_COLLISION = 'CONTENT_SYSTEM__PROVIDER_DELIVERY_COLLISION';
    public const ROUTES_ALREADY_LOADED = 'CONTENT_SYSTEM__ROUTES_ALREADY_LOADED';
    public const MISSING_EXTENDS_ANNOTATION = 'CONTENT_SYSTEM__MISSING_EXTENDS_ANNOTATION';
    public const UNSUPPORTED_TYPE_NODE = 'CONTENT_SYSTEM__UNSUPPORTED_TYPE_NODE';
    public const UNRESOLVABLE_TYPE_CLASS = 'CONTENT_SYSTEM__UNRESOLVABLE_TYPE_CLASS';
    public const ELEMENT_TYPE_DUPLICATE = 'CONTENT_SYSTEM__ELEMENT_TYPE_DUPLICATE';
    public const ELEMENT_TYPES_INVALID = 'CONTENT_SYSTEM__ELEMENT_TYPES_INVALID';
    public const ELEMENT_TYPE_LOAD_FAILED = 'CONTENT_SYSTEM__ELEMENT_TYPE_LOAD_FAILED';
    public const ELEMENT_TYPE_NOT_FOUND = 'CONTENT_SYSTEM__ELEMENT_TYPE_NOT_FOUND';
    public const ELEMENT_TYPE_INVALID_FILENAME = 'CONTENT_SYSTEM__ELEMENT_TYPE_INVALID_FILENAME';
    public const UNKNOWN_ENTITY_TYPE = 'CONTENT_SYSTEM__UNKNOWN_ENTITY_TYPE';
    public const UNKNOWN_LOADER_ENTITY = 'CONTENT_SYSTEM__UNKNOWN_LOADER_ENTITY';
    public const ENTITY_TYPE_RESOLUTION_UNSUPPORTED = 'CONTENT_SYSTEM__ENTITY_TYPE_RESOLUTION_UNSUPPORTED';
    public const INVALID_LAYOUT_STRUCTURE = 'CONTENT_SYSTEM__INVALID_LAYOUT_STRUCTURE';
    public const MUTATION_TARGET_NOT_FOUND = 'CONTENT_SYSTEM__MUTATION_TARGET_NOT_FOUND';
    public const MUTATION_CYCLE = 'CONTENT_SYSTEM__MUTATION_CYCLE';
    public const MUTATION_SLOT_REQUIRED = 'CONTENT_SYSTEM__MUTATION_SLOT_REQUIRED';
    public const MUTATION_INVALID_WRAP_TARGETS = 'CONTENT_SYSTEM__MUTATION_INVALID_WRAP_TARGETS';
    public const MUTATION_UNKNOWN_TYPE = 'CONTENT_SYSTEM__MUTATION_UNKNOWN_TYPE';
    public const LAYOUT_VERSION_CONFLICT = 'CONTENT_SYSTEM__LAYOUT_VERSION_CONFLICT';
    public const INVALID_VERSION_TOKEN = 'CONTENT_SYSTEM__INVALID_VERSION_TOKEN';
    public const CONTENT_LAYOUT_NOT_FOUND = 'CONTENT_SYSTEM__CONTENT_LAYOUT_NOT_FOUND';
    public const PREVIEW_PAYLOAD_STORE_FAILED = 'CONTENT_SYSTEM__PREVIEW_PAYLOAD_STORE_FAILED';
    public const PREVIEW_PAYLOAD_INVALID = 'CONTENT_SYSTEM__PREVIEW_PAYLOAD_INVALID';
    public const UNKNOWN_ROOT_SOURCE = 'CONTENT_SYSTEM__UNKNOWN_ROOT_SOURCE';
    public const ROOT_SOURCE_RESOLUTION_UNSUPPORTED = 'CONTENT_SYSTEM__ROOT_SOURCE_RESOLUTION_UNSUPPORTED';
    public const NONE_SOURCE_NOT_RENDERABLE = 'CONTENT_SYSTEM__NONE_SOURCE_NOT_RENDERABLE';
    public const ROOT_SOURCE_ASSIGNMENT_MISMATCH = 'CONTENT_SYSTEM__ROOT_SOURCE_ASSIGNMENT_MISMATCH';
    public const UNKNOWN_REQUEST_FIELD = 'CONTENT_SYSTEM__UNKNOWN_REQUEST_FIELD';
    public const UNSUPPORTED_STYLE_VALUE_TYPE = 'CONTENT_SYSTEM__UNSUPPORTED_STYLE_VALUE_TYPE';
    public const STYLE_OPTION_DUPLICATE = 'CONTENT_SYSTEM__STYLE_OPTION_DUPLICATE';
    public const STYLE_OPTIONS_INVALID = 'CONTENT_SYSTEM__STYLE_OPTIONS_INVALID';
    public const STYLE_OPTION_LOAD_FAILED = 'CONTENT_SYSTEM__STYLE_OPTION_LOAD_FAILED';
    public const STYLE_OPTION_INVALID_FILENAME = 'CONTENT_SYSTEM__STYLE_OPTION_INVALID_FILENAME';
    public const BINDING_SPECIFICATION_DUPLICATE = 'CONTENT_SYSTEM__BINDING_SPECIFICATION_DUPLICATE';
    public const BINDING_SPECIFICATION_LOAD_FAILED = 'CONTENT_SYSTEM__BINDING_SPECIFICATION_LOAD_FAILED';
    public const BINDING_SPECIFICATIONS_INVALID = 'CONTENT_SYSTEM__BINDING_SPECIFICATIONS_INVALID';
    public const BINDING_SPECIFICATION_NOT_FOUND = 'CONTENT_SYSTEM__BINDING_SPECIFICATION_NOT_FOUND';
    public const BINDING_TYPE_MISMATCH = 'CONTENT_SYSTEM__BINDING_TYPE_MISMATCH';
    public const BINDING_SPECIFICATION_UNKNOWN_TYPE = 'CONTENT_SYSTEM__BINDING_SPECIFICATION_UNKNOWN_TYPE';
    public const BINDING_SPECIFICATION_CANONICALIZATION_FAILED = 'CONTENT_SYSTEM__BINDING_SPECIFICATION_CANONICALIZATION_FAILED';
    public const BINDING_SPECIFICATION_RESERVED_ID = 'CONTENT_SYSTEM__BINDING_SPECIFICATION_RESERVED_ID';
    public const BINDING_SPECIFICATION_DEFAULT_AMBIGUOUS = 'CONTENT_SYSTEM__BINDING_SPECIFICATION_DEFAULT_AMBIGUOUS';
    public const BOX_SPACING_TOKENIZATION_FAILED = 'CONTENT_SYSTEM__BOX_SPACING_TOKENIZATION_FAILED';
    public const LAYOUT_WRITE_MEMO_MISSING = 'CONTENT_SYSTEM__LAYOUT_WRITE_MEMO_MISSING';
    public const LOADER_INPUT_NOT_DECLARED = 'CONTENT_SYSTEM__LOADER_INPUT_NOT_DECLARED';
    public const LOADER_INPUT_UNRESOLVED = 'CONTENT_SYSTEM__LOADER_INPUT_UNRESOLVED';
    public const LOADER_INPUT_TYPE_MISMATCH = 'CONTENT_SYSTEM__LOADER_INPUT_TYPE_MISMATCH';
    public const LOADER_CONFIG_KEY_WITHOUT_PROPERTY = 'CONTENT_SYSTEM__LOADER_CONFIG_KEY_WITHOUT_PROPERTY';
    public const RESOLVED_VALUE_INDEX_MISSING = 'CONTENT_SYSTEM__RESOLVED_VALUE_INDEX_MISSING';
    public const FIELD_SELECTION_NOT_SUPPORTED = 'CONTENT_SYSTEM__FIELD_SELECTION_NOT_SUPPORTED';
    public const UNSUPPORTED_PROPERTY_VALUE_TYPE = 'CONTENT_SYSTEM__UNSUPPORTED_PROPERTY_VALUE_TYPE';
    public const INVALID_ELEMENT_ID = 'CONTENT_SYSTEM__INVALID_ELEMENT_ID';

    /**
     * Error codes that mark a defect in client-supplied layout input rather than an internal fault; the
     * diagnostics layer and the draft decode path map only these per element to a client-facing 400 and let
     * every other code propagate, so an internal fault is never relabelled as the client's mistake.
     *
     * {@see INVALID_MAP_KEY} is one of them because a JSON object member named "5" arrives as an integer PHP
     * array key: a numeric property, data-requirement, slot or context key is a malformed payload the client
     * sent, which is why the DAL write path already rejects it as a layout write rejection rather than a fault.
     */
    public const CLIENT_DEFECT_CODES = [
        self::DATA_LOADER_NOT_REGISTERED,
        self::CONFIG_SERIALIZER_NOT_REGISTERED,
        self::UNKNOWN_LOADER_ENTITY,
        self::INVALID_FIELD_VALUE_TYPE,
        self::INVALID_FIELD_VALUE_RANGE,
        self::CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE,
        self::PROPERTY_ALIAS_WITH_DOT_NOTATION,
        self::PROVIDER_DELIVERY_COLLISION,
        self::INVALID_MAP_KEY,
        self::INVALID_ELEMENT_ID,
    ];

    public static function isClientDefect(\Throwable $exception): bool
    {
        return $exception instanceof self
            && \in_array($exception->getErrorCode(), self::CLIENT_DEFECT_CODES, true);
    }

    public static function dataLoaderNotRegistered(string $requirementType, string $elementType, string $elementId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_NOT_REGISTERED,
            'Data loader for requirement type "{{ requirementType }}" not registered. Element type: "{{ elementType }}", element ID: "{{ elementId }}"',
            ['requirementType' => $requirementType, 'elementType' => $elementType, 'elementId' => $elementId]
        );
    }

    // $elementId names the element whose data requirement or specification wiring named the unregistered
    // source, so a client can remove the stale wiring deliberately rather than guessing which element to fix.
    // Optional because the two DataLoaderConfigSerializerProvider throw sites hold no element id at all; a
    // caller that does hold one (AttributionReconciler, StoredElementCodec) re-throws with it added.
    public static function configSerializerNotRegistered(string $source, ?string $elementId = null): self
    {
        $message = 'Config serializer for source "{{ source }}" is not registered';
        if ($elementId !== null) {
            $message .= '. Element ID: "{{ elementId }}"';
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONFIG_SERIALIZER_NOT_REGISTERED,
            $message,
            ['source' => $source, 'elementId' => $elementId]
        );
    }

    /**
     * An element id outside the value domain the decode gate admits. Two values are excluded: the reserved
     * literal {@see VirtualRootWrapper::VIRTUAL_ROOT_ID}, which an authored element carrying it would collide
     * with on every wrapping render, and a string PHP casts to an integer array key, which puts an integer key
     * into {@see ResolvedValueIndexFactory}'s string-keyed assignments map — encoding as a JSON list once those
     * keys happen to run 0..n-1, and as a map with integer-looking members otherwise.
     *
     * A 500 while still in CLIENT_DEFECT_CODES, the same split {@see invalidFieldValueType()} and
     * {@see invalidMapKey()} take, because a decode-time throw has four audiences and this status answers only
     * the last of them:
     *
     * - the DAL write wraps every {@see ContentSystemException} into a `WriteConstraintViolationException`
     *   ({@see StoredElementListFieldSerializer::normalize()}), and that is a 400 whatever the code says —
     *   catalogue membership decides nothing here;
     * - the strict draft decode ({@see DraftLayoutDecoder::decode()}) re-raises a catalogued code as
     *   `invalidLayoutStructure`, a 400, and lets an uncatalogued one propagate;
     * - the lintable decode the diagnose route runs ({@see DraftLayoutDecoder::decodeLintable()}) collects a
     *   catalogued code as an `invalid_config` violation and answers 200;
     * - the stored-column read catches nothing, so this status IS the response. A rejected id there means
     *   corrupt stored data — an internal fault, on the same argument {@see duplicateElementId()} makes,
     *   though that one is deliberately outside the catalogue while this one is in it.
     */
    public static function invalidElementId(string $id, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_ELEMENT_ID,
            'Element id "{{ id }}" is not accepted: {{ reason }}.',
            ['id' => $id, 'reason' => $reason]
        );
    }

    public static function invalidFieldType(string $expectedClass, string $actualClass): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_FIELD_TYPE,
            'Expected field of type {{ expectedClass }}, got {{ actualClass }}',
            ['expectedClass' => $expectedClass, 'actualClass' => $actualClass]
        );
    }

    public static function invalidFieldValueType(string $fieldName, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_FIELD_VALUE_TYPE,
            'Field {{ fieldName }} expected {{ expectedType }}, got {{ actualType }}',
            ['fieldName' => $fieldName, 'expectedType' => $expectedType, 'actualType' => $actualType]
        );
    }

    public static function invalidFieldValueRange(string $fieldName, int $minimum, int $actual): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_FIELD_VALUE_RANGE,
            'Field {{ fieldName }} expected a minimum of {{ minimum }}, got {{ actual }}',
            ['fieldName' => $fieldName, 'minimum' => $minimum, 'actual' => $actual]
        );
    }

    public static function invalidLoaderConfig(string $source, \Throwable $previous): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_FIELD_VALUE_TYPE,
            'Invalid configuration for data loader source "{{ source }}": {{ reason }}',
            ['source' => $source, 'reason' => $previous->getMessage()],
            $previous,
        );
    }

    /**
     * The four loader-input faults below are loader authoring bugs, never client-supplied layout defects, and
     * are therefore deliberately absent from CLIENT_DEFECT_CODES.
     *
     * @param list<string> $declaredKeys
     */
    public static function loaderInputNotDeclared(string $key, array $declaredKeys): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LOADER_INPUT_NOT_DECLARED,
            'Loader input "{{ key }}" was never declared in the loader\'s configSpecification(). Declared inputs: "{{ declaredKeys }}"',
            ['key' => $key, 'declaredKeys' => implode('", "', $declaredKeys)]
        );
    }

    public static function loaderInputUnresolved(string $key): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LOADER_INPUT_UNRESOLVED,
            'Loader input "{{ key }}" is unresolved. Declare a default for it or read it through a nullable accessor',
            ['key' => $key]
        );
    }

    public static function loaderInputTypeMismatch(string $key, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LOADER_INPUT_TYPE_MISMATCH,
            'Loader input "{{ key }}" was read as {{ expectedType }}, but resolved to {{ actualType }}',
            ['key' => $key, 'expectedType' => $expectedType, 'actualType' => $actualType]
        );
    }

    public static function loaderConfigKeyWithoutProperty(string $configClass, string $key): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LOADER_CONFIG_KEY_WITHOUT_PROPERTY,
            'Config class "{{ configClass }}" has no public property for the declared config key "{{ key }}"',
            ['configClass' => $configClass, 'key' => $key]
        );
    }

    public static function invalidMapKey(string $mapType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_MAP_KEY,
            '{{ mapType }} key must be string, got {{ actualType }}',
            ['mapType' => $mapType, 'actualType' => $actualType]
        );
    }

    /**
     * A breakpoint key no {@see Breakpoint} case declares. It carries INVALID_MAP_KEY like every other
     * malformed wiring key, but not {@see invalidMapKey()}'s message: that one reads "key must be string",
     * which is false here — an unknown breakpoint key is a perfectly good string that names nothing.
     *
     * @param list<string> $allowed
     */
    public static function unknownStyleBreakpoint(string $option, string $breakpoint, array $allowed): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_MAP_KEY,
            'Style option "{{ option }}" has no breakpoint "{{ breakpoint }}"; expected one of {{ allowed }}.',
            ['option' => $option, 'breakpoint' => $breakpoint, 'allowed' => implode(', ', $allowed)]
        );
    }

    public static function invalidMapValue(string $mapType, string $key, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_MAP_VALUE,
            '{{ mapType }} value for "{{ key }}" must be {{ expectedType }}, got {{ actualType }}',
            ['mapType' => $mapType, 'key' => $key, 'expectedType' => $expectedType, 'actualType' => $actualType]
        );
    }

    /**
     * A producer defect rather than client input, so it is a 500 and deliberately absent from
     * {@see self::CLIENT_DEFECT_CODES}: whatever filled the property handed over a value type the rendered
     * model does not admit, which no layout a client can send produces on its own.
     */
    public static function unsupportedPropertyValueType(string $key, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNSUPPORTED_PROPERTY_VALUE_TYPE,
            'Rendered element property "{{ key }}" holds a value of unsupported type {{ actualType }}; permitted are scalars, null, arrays of those, Struct, DateTimeInterface and BackedEnum',
            ['key' => $key, 'actualType' => $actualType]
        );
    }

    public static function layoutAssignmentNotFound(string $entityType, string $entityId, string $salesChannelId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::LAYOUT_ASSIGNMENT_NOT_FOUND,
            'No layout assignment found for {{ entityType }} "{{ entityId }}" in sales channel "{{ salesChannelId }}"',
            ['entityType' => $entityType, 'entityId' => $entityId, 'salesChannelId' => $salesChannelId]
        );
    }

    // Distinct from contentLayoutNotFound() on purpose: this is the Store-API render-time 500 (a missing layout
    // here is a configuration error), while contentLayoutNotFound() is the Admin mutation 404 for an unknown {layoutId}.
    public static function layoutNotFound(string $layoutId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LAYOUT_NOT_FOUND,
            'Content layout with ID "{{ layoutId }}" does not exist. This indicates a configuration error.',
            ['layoutId' => $layoutId]
        );
    }

    public static function elementNotFound(string $elementId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ELEMENT_NOT_FOUND,
            'Element with ID "{{ elementId }}" not found in layout',
            ['elementId' => $elementId]
        );
    }

    /**
     * A served layout is stored data, not client input, so a corrupt one is an internal fault rather than a
     * client defect: deliberately absent from {@see self::CLIENT_DEFECT_CODES}. Element ids are unique across
     * a forest by contract, and the DAL write enforces it through `StoredTree::validate()`. The read path runs
     * no validation, so a raw-SQL or migration write, or a preparation listener replacing the stored tree, can
     * put a repeated id in front of a consumer whose correctness depends on the invariant — and so can a
     * finalization listener replacing the rendered tree, which is why the rendered forest is checked as well
     * as the stored one.
     */
    public static function duplicateElementId(string $elementId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_ELEMENT_ID,
            'Served forest is corrupt: element ID "{{ elementId }}" appears more than once, and element IDs must be unique across a forest. Re-save the layout through the DAL write, which rejects a repeated ID, and make sure no rendering listener that replaces the tree introduces one.',
            ['elementId' => $elementId]
        );
    }

    /**
     * An internal fault, never client input: a context delivery index is total over the forest it was built
     * from, so a missing element id means the index and the tree being rendered came from different forests.
     * Deliberately not a 404 and deliberately absent from {@see self::CLIENT_DEFECT_CODES} — no layout a
     * client can send produces this.
     */
    public static function contextDeliveryMissing(string $elementId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONTEXT_DELIVERY_MISSING,
            'No context delivery recorded for element "{{ elementId }}"; the delivery index was built from a different forest',
            ['elementId' => $elementId]
        );
    }

    /**
     * An internal fault, never a client defect: the pipeline builds a resolved-value index whenever the served
     * format asks for one, and every format that reads the index asks, so a render result reaching an
     * index-reading encoder without one is a broken wiring invariant. Deliberately absent from
     * {@see self::CLIENT_DEFECT_CODES} — a served layout is stored data, not client input.
     */
    public static function resolvedValueIndexMissing(string $layoutId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::RESOLVED_VALUE_INDEX_MISSING,
            'The render result for layout "{{ layoutId }}" carries no resolved-value index, but the format being served requires one.',
            ['layoutId' => $layoutId]
        );
    }

    /**
     * The 400 for a content request carrying `includes` or `excludes`. Field selection is not part of the
     * content-route contract, so the parameter is refused by name rather than stripped and served around.
     *
     * Deliberately absent from {@see CLIENT_DEFECT_CODES}: that list classifies defects in client-supplied
     * layout data, and diagnostics never sees this rejection.
     */
    public static function fieldSelectionNotSupported(string $parameter): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FIELD_SELECTION_NOT_SUPPORTED,
            'Field selection is not supported by this route. Remove the "{{ parameter }}" parameter from the request.',
            ['parameter' => $parameter]
        );
    }

    public static function noFactoryCanHandle(string $path): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NO_FACTORY_CAN_HANDLE,
            'No context factory can handle the request for path "{{ path }}"',
            ['path' => $path]
        );
    }

    public static function invalidEntityPath(string $entityType, string $path, string $expectedFormat): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_ENTITY_PATH,
            'Invalid {{ entityType }} path format: "{{ path }}". Expected format: {{ expectedFormat }}',
            [
                'entityType' => $entityType,
                'path' => $path,
                'expectedFormat' => $expectedFormat,
            ]
        );
    }

    public static function redistributeWithDottedPath(string $contextKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::REDISTRIBUTE_DOTTED_PATH,
            'Context key "{{ key }}" uses dot notation and cannot be redistributed. Only base keys support redistribution.',
            ['key' => $contextKey]
        );
    }

    public static function redistributeConflict(string $contextKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::REDISTRIBUTE_CONFLICT,
            'Context key "{{ key }}" has both redistribute:true and explicit providesContext. Use one or the other.',
            ['key' => $contextKey]
        );
    }

    public static function consumerAliasWithoutRedistribute(string $contextKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE,
            'Context key "{{ key }}" has consumerAlias but redistribute is not true. consumerAlias requires redistribute:true.',
            ['key' => $contextKey]
        );
    }

    public static function contextPathNotResolvable(string $fullPath, string $elementId, ?string $reason = null): self
    {
        $message = 'Cannot resolve context path "{{ fullPath }}" for element "{{ elementId }}"';
        if ($reason !== null) {
            $message .= ': {{ reason }}';
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONTEXT_PATH_NOT_RESOLVABLE,
            $message,
            ['fullPath' => $fullPath, 'elementId' => $elementId, 'reason' => $reason]
        );
    }

    public static function propertyAliasWithDotNotation(string $contextKey, string $propertyAlias): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROPERTY_ALIAS_WITH_DOT_NOTATION,
            'Context key "{{ key }}" has propertyAlias "{{ alias }}" with dot notation. Property aliases must be simple property names without dots.',
            ['key' => $contextKey, 'alias' => $propertyAlias]
        );
    }

    public static function propertyAliasCollision(string $propertyKey, string $firstContext, string $secondContext): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROPERTY_ALIAS_COLLISION,
            'Property key "{{ propertyKey }}" is used by both context "{{ firstContext }}" and "{{ secondContext }}". Each propertyAlias must be unique within an element.',
            ['propertyKey' => $propertyKey, 'firstContext' => $firstContext, 'secondContext' => $secondContext]
        );
    }

    /**
     * The 400 for two providers of one element that deliver to children under the same child-facing key.
     *
     * The child-facing key is the key the distributor matches children on: an authored provider's is
     * `distributionConfig->getConsumerAlias() ?? providerKey`, a redistribute consumer's derived provider's
     * is `consumerAlias ?? contextKey`. Two providers sharing it both deliver to the same children and the
     * later one silently wins by iteration order, so the serving path and the write gate reject the layout
     * instead.
     *
     * $first and $second name the colliding providers: the provider map key for an authored provider, the
     * consumer context key for a derived one.
     *
     * $elementId is the element that DECLARES both providers. It is carried as data only and has no
     * placeholder in the message: {@see LayoutDiagnostics::analyze()} reads it back to stamp the
     * violation with the declaring element rather than the element it happens to be looping over.
     */
    public static function providerDeliveryCollision(string $childKey, string $first, string $second, string $elementId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROVIDER_DELIVERY_COLLISION,
            'Child-facing key "{{ childKey }}" is used by both "{{ first }}" and "{{ second }}". Each child-facing key must be unique within an element.',
            ['childKey' => $childKey, 'first' => $first, 'second' => $second, 'elementId' => $elementId]
        );
    }

    public static function missingExtendsAnnotation(string $loaderClass): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_EXTENDS_ANNOTATION,
            'Data loader "{{ loaderClass }}" is missing @extends AbstractContentDataLoader<T> annotation.',
            ['loaderClass' => $loaderClass]
        );
    }

    public static function unsupportedTypeNode(string $nodeClass): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNSUPPORTED_TYPE_NODE,
            'Unsupported type node "{{ nodeClass }}" in @extends annotation.',
            ['nodeClass' => $nodeClass]
        );
    }

    public static function unresolvableTypeClass(string $resolvedName, string $loaderClass): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNRESOLVABLE_TYPE_CLASS,
            'Resolved type "{{ resolvedName }}" in @extends annotation of "{{ loaderClass }}" is not a subclass of Struct.',
            ['resolvedName' => $resolvedName, 'loaderClass' => $loaderClass]
        );
    }

    public static function routesAlreadyLoaded(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ROUTES_ALREADY_LOADED,
            'Content system routes are already loaded.'
        );
    }

    public static function elementTypeDuplicate(string $name, string $existingSource, string $newSource): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::ELEMENT_TYPE_DUPLICATE,
            'Element type "{{ name }}" is already registered by "{{ existingSource }}", cannot register again from "{{ newSource }}"',
            ['name' => $name, 'existingSource' => $existingSource, 'newSource' => $newSource]
        );
    }

    public static function elementTypeLoadFailed(string $file, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ELEMENT_TYPE_LOAD_FAILED,
            'Failed to load element type from "{{ file }}": {{ reason }}',
            ['file' => $file, 'reason' => $reason],
            $previous
        );
    }

    public static function elementTypesInvalid(ConstraintViolationListInterface $violations): self
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ELEMENT_TYPES_INVALID,
            'Element type validation failed: {{ reason }}',
            ['reason' => implode('; ', $messages)]
        );
    }

    public static function elementTypeInvalidFilename(string $segment, string $file): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ELEMENT_TYPE_INVALID_FILENAME,
            'Invalid element type filename segment "{{ segment }}" in file "{{ file }}". Segments must match [a-z0-9]+(-[a-z0-9]+)*',
            ['segment' => $segment, 'file' => $file]
        );
    }

    public static function elementTypeNotFound(string $name): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ELEMENT_TYPE_NOT_FOUND,
            'Element type "{{ name }}" not found',
            ['name' => $name]
        );
    }

    public static function unknownEntityType(string $entityType): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNKNOWN_ENTITY_TYPE,
            'No content layout specification source can handle entity type "{{ entityType }}"',
            ['entityType' => $entityType]
        );
    }

    public static function unknownLoaderEntity(string $entityName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNKNOWN_LOADER_ENTITY,
            'The entity loader cannot resolve a produced type for unknown entity "{{ entityName }}".',
            ['entityName' => $entityName]
        );
    }

    public static function entityTypeResolutionUnsupported(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ENTITY_TYPE_RESOLUTION_UNSUPPORTED,
            'resolveSpecificationDataForEntity() must only be called on a source whose supportsEntityType() returns true.'
        );
    }

    public static function invalidLayoutStructure(ConstraintViolationListInterface $violations): self
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_LAYOUT_STRUCTURE,
            'Invalid layout structure: {{ reason }}',
            ['reason' => implode('; ', $messages)]
        );
    }

    public static function mutationTargetNotFound(string $elementId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MUTATION_TARGET_NOT_FOUND,
            'Element "{{ elementId }}" was not found in the layout.',
            ['elementId' => $elementId]
        );
    }

    public static function mutationCycle(string $elementId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MUTATION_CYCLE,
            'Cannot move element "{{ elementId }}" into itself or one of its descendants.',
            ['elementId' => $elementId]
        );
    }

    public static function mutationSlotRequired(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MUTATION_SLOT_REQUIRED,
            'A slot must be supplied to place the element into a parent.'
        );
    }

    public static function mutationInvalidWrapTargets(string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MUTATION_INVALID_WRAP_TARGETS,
            'Cannot wrap the given elements: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function mutationUnknownType(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MUTATION_UNKNOWN_TYPE,
            'Element type "{{ type }}" is not a registered element type.',
            ['type' => $type]
        );
    }

    public static function layoutVersionConflict(string $layoutId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::LAYOUT_VERSION_CONFLICT,
            'Content layout "{{ layoutId }}" was modified concurrently; the expected version no longer matches. Reload the layout and retry.',
            ['layoutId' => $layoutId]
        );
    }

    public static function invalidVersionToken(string $expectedVersion): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_VERSION_TOKEN,
            'The expected version token "{{ expectedVersion }}" is not a valid date-time. Reload the layout and retry with the value the Admin API returned.',
            ['expectedVersion' => $expectedVersion]
        );
    }

    // Distinct from layoutNotFound() on purpose: this is the Admin mutation 404 for an unknown {layoutId}, while
    // layoutNotFound() is the Store-API render-time 500 for a layout that should exist but does not.
    public static function contentLayoutNotFound(string $layoutId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CONTENT_LAYOUT_NOT_FOUND,
            'Content layout "{{ layoutId }}" was not found.',
            ['layoutId' => $layoutId]
        );
    }

    public static function previewPayloadStoreFailed(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PREVIEW_PAYLOAD_STORE_FAILED,
            'Could not store the content preview payload.'
        );
    }

    /**
     * The server fault for a redeemed preview token whose stored envelope is not a preview request. The store
     * writes the envelope from an already-validated DTO and the mint request rejects everything the envelope
     * cannot hold, so a malformed hit is server-side state — cache corruption, or a DTO field-set change
     * deployed inside the five-minute TTL — and never something the redeeming caller sent. The redemption
     * route refuses it rather than substituting a default and rendering silently emptied data. A token that
     * addresses no entry is a separate case: `load()` answers `null` and the route reports a 404. The sibling
     * precedent for the status is {@see previewPayloadStoreFailed()}. Deliberately not in CLIENT_DEFECT_CODES:
     * that list is only for element-tree config defects the diagnostics kernel catches per element.
     */
    public static function previewPayloadInvalid(string $field, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PREVIEW_PAYLOAD_INVALID,
            'The stored preview payload field "{{ field }}" must be {{ expectedType }}, got {{ actualType }}.',
            ['field' => $field, 'expectedType' => $expectedType, 'actualType' => $actualType]
        );
    }

    /**
     * The client-facing 400 for an admin content request that carries a field its payload DTO does not declare
     * (e.g. the removed entityType/section, or a typo). The draft/mutation routes opt into strict request mapping,
     * and UnknownRequestFieldExceptionListener remaps the serializer's ExtraAttributesException to this so a stale
     * or mistyped field fails fast rather than being silently dropped.
     *
     * @param list<string> $fields
     */
    public static function unknownRequestField(array $fields): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNKNOWN_REQUEST_FIELD,
            'The request contains unknown field(s): {{ fields }}. This endpoint rejects fields it does not declare.',
            ['fields' => implode(', ', $fields)]
        );
    }

    // The client-facing 400 for a written root source that is not a member of the registry. Distinct from
    // rootSourceResolutionUnsupported(): membership is gated with this 400 before resolve()/sourceFor() is ever
    // reached, on both the create and edit content_layout write paths, and the diagnose/mutation routes.
    public static function unknownRootSource(string $rootSource): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNKNOWN_ROOT_SOURCE,
            'Unknown root source "{{ rootSource }}". It is not a registered root source.',
            ['rootSource' => $rootSource]
        );
    }

    // The internal 500 fail-hard when RootSourceRegistry::sourceFor()/resolve() is handed an id absent from
    // knownRootSources(). Not a client fault: every runtime caller gates membership first, so reaching this is a
    // programming error in a new, ungated caller.
    public static function rootSourceResolutionUnsupported(string $rootSource): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ROOT_SOURCE_RESOLUTION_UNSUPPORTED,
            'Root source "{{ rootSource }}" is not registered and cannot be resolved. Validate membership via RootSourceRegistry::knownRootSources() before resolving.',
            ['rootSource' => $rootSource]
        );
    }

    // The "none" source resolves no layout and exposes no rendering path; RenderingSpecificationResolver gates on
    // supports()/supportsEntityType() (both false), so these methods are unreachable and fail hard if ever reached.
    public static function noneSourceNotRenderable(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NONE_SOURCE_NOT_RENDERABLE,
            'The "none" root source resolves no layout and exposes no rendering path; this resolution method must never be called on it.'
        );
    }

    // The client-facing 400 surfaced as the assignment write violation when an entity/section is bound to a layout
    // whose immutable root source is a different page kind. Assignment is a tree-blind type-match against rootSource.
    public static function rootSourceAssignmentMismatch(string $rootSource, string $assignmentType): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ROOT_SOURCE_ASSIGNMENT_MISMATCH,
            'Cannot assign a "{{ assignmentType }}" entity to a content layout whose root source is "{{ rootSource }}".',
            ['rootSource' => $rootSource, 'assignmentType' => $assignmentType]
        );
    }

    public static function styleOptionDuplicate(string $name, string $existingSource, string $newSource): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::STYLE_OPTION_DUPLICATE,
            'Style option "{{ name }}" is already registered by "{{ existingSource }}", cannot register again from "{{ newSource }}"',
            ['name' => $name, 'existingSource' => $existingSource, 'newSource' => $newSource]
        );
    }

    public static function styleOptionsInvalid(ConstraintViolationListInterface $violations): self
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::STYLE_OPTIONS_INVALID,
            'Style option validation failed: {{ reason }}',
            ['reason' => implode('; ', $messages)]
        );
    }

    public static function styleOptionLoadFailed(string $file, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::STYLE_OPTION_LOAD_FAILED,
            'Failed to load style option from "{{ file }}": {{ reason }}',
            ['file' => $file, 'reason' => $reason],
            $previous
        );
    }

    public static function styleOptionInvalidFilename(string $name, string $file): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::STYLE_OPTION_INVALID_FILENAME,
            'Invalid style option filename "{{ name }}" in file "{{ file }}". The option name must match [a-z0-9]+(-[a-z0-9]+)*',
            ['name' => $name, 'file' => $file]
        );
    }

    // The internal 500 fail-hard when StyleOptionConstraintDeriver is handed a value type whose primitive is not one
    // of StyleOptionValueType::PRIMITIVE_TYPES. Unreachable in practice: every registered option passes the DTO's
    // Choice/TypedStyleOption validation first, so reaching this is a programming error in a new, ungated caller.
    public static function unsupportedStyleValueType(string $type): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNSUPPORTED_STYLE_VALUE_TYPE,
            'Style option value type "{{ type }}" is not a supported primitive (string, integer, number, boolean).',
            ['type' => $type]
        );
    }

    public static function bindingSpecificationDuplicate(string $id, string $existingSource, string $newSource): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::BINDING_SPECIFICATION_DUPLICATE,
            'Binding specification "{{ id }}" is already registered by "{{ existingSource }}", cannot register again from "{{ newSource }}"',
            ['id' => $id, 'existingSource' => $existingSource, 'newSource' => $newSource]
        );
    }

    public static function bindingSpecificationLoadFailed(string $path, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::BINDING_SPECIFICATION_LOAD_FAILED,
            'Failed to load binding specification from "{{ path }}": {{ reason }}',
            ['path' => $path, 'reason' => $reason],
            $previous
        );
    }

    public static function bindingSpecificationsInvalid(ConstraintViolationListInterface $violations): self
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::BINDING_SPECIFICATIONS_INVALID,
            'Binding specification validation failed: {{ reason }}',
            ['reason' => implode('; ', $messages)]
        );
    }

    // The load-time 400 for a specification whose declared (or inline-implicit) type is not a registered element
    // type. The canonicalizer needs the type for every specification it processes (sugared or canonical), so an
    // unknown type is rejected here rather than deferred to TypeConsistentBindingSpecification. Deliberately not
    // in CLIENT_DEFECT_CODES: that list is only for element-tree config defects the diagnostics kernel catches, not
    // authored-artifact load errors (matching bindingSpecificationNotFound/bindingTypeMismatch).
    public static function bindingSpecificationUnknownType(string $id, string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::BINDING_SPECIFICATION_UNKNOWN_TYPE,
            'Binding specification "{{ id }}" declares the unknown element type "{{ type }}".',
            ['id' => $id, 'type' => $type]
        );
    }

    // The load-time 400 for any sugar that cannot expand deterministically (unrecognized resolves shape, mixed
    // loader/source keys, ambiguous or zero eligible tier-A sources, ambiguous or zero entity-name derivation,
    // unknown tier-B config key). The reason carries the mechanical fix the author must apply. Deliberately not in
    // CLIENT_DEFECT_CODES, for the same reason as bindingSpecificationUnknownType.
    public static function bindingSpecificationCanonicalizationFailed(string $id, string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::BINDING_SPECIFICATION_CANONICALIZATION_FAILED,
            'Cannot canonicalize binding specification "{{ id }}": {{ reason }}',
            ['id' => $id, 'reason' => $reason]
        );
    }

    // The load-time 409 for an authored `bindings:` map key equal to its containing file's implicit type name.
    // That id is reserved for the type's synthesized default specification (DefaultBindingSpecificationSynthesizer),
    // so an authored entry cannot impersonate it or carry authored inputs. Applies unconditionally, whether or
    // not the file actually synthesizes a default.
    public static function bindingSpecificationReservedId(string $id, string $type, string $path): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::BINDING_SPECIFICATION_RESERVED_ID,
            'Binding specification id "{{ id }}" in "{{ path }}" is reserved for the synthesized default of element type "{{ type }}"; choose a different id.',
            ['id' => $id, 'type' => $type, 'path' => $path]
        );
    }

    // The client-facing 400 for a bind-element mutation whose bindingSpecificationId is not a registered
    // specification. The id is a request body value (not a path lookup), so this follows the same body-parameter
    // 400 convention as the other mutation structural errors (mutationTargetNotFound, mutationUnknownType), not
    // the 404 path-lookup convention of contentLayoutNotFound().
    public static function bindingSpecificationNotFound(string $bindingSpecificationId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::BINDING_SPECIFICATION_NOT_FOUND,
            'Binding specification "{{ bindingSpecificationId }}" was not found.',
            ['bindingSpecificationId' => $bindingSpecificationId]
        );
    }

    public static function bindingTypeMismatch(string $bindingSpecificationId, string $specificationType, string $elementComponent): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::BINDING_TYPE_MISMATCH,
            'Binding specification "{{ bindingSpecificationId }}" applies to type "{{ specificationType }}", but the target element is of type "{{ elementComponent }}".',
            ['bindingSpecificationId' => $bindingSpecificationId, 'specificationType' => $specificationType, 'elementComponent' => $elementComponent]
        );
    }

    /**
     * The 409 for a type whose default set (byType(type) filtered by isDefault()) holds more than one
     * specification: the ops that fill-apply a type's default throw rather than pick one.
     *
     * @param list<string> $qualifiedIds
     */
    public static function bindingSpecificationDefaultAmbiguous(string $type, array $qualifiedIds): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::BINDING_SPECIFICATION_DEFAULT_AMBIGUOUS,
            'Element type "{{ type }}" has more than one default binding specification ({{ qualifiedIds }}), but at most one specification may be default per type.',
            ['type' => $type, 'qualifiedIds' => implode(', ', $qualifiedIds)]
        );
    }

    /**
     * The internal 500 for a PCRE failure while tokenizing a box-spacing style value into its four sides —
     * a malformed-UTF-8 subject, or a backtrack/recursion limit on a large one. BoxSpacingNormalizer throws
     * instead of substituting a plausible-looking split, because a substituted split is indistinguishable
     * from a real one and would be stored as if it were the authored value.
     *
     * The value is identified by its byte length and a content fingerprint rather than echoed: the message
     * must stay bounded, and the very inputs that reach this path may not be valid UTF-8 to begin with.
     */
    public static function boxSpacingTokenizationFailed(string $operation, string $value, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::BOX_SPACING_TOKENIZATION_FAILED,
            'Could not {{ operation }} a box-spacing value: {{ reason }}. Value length: {{ length }} bytes, fingerprint: {{ fingerprint }}.',
            [
                'operation' => $operation,
                'reason' => $reason,
                'length' => (string) \strlen($value),
                'fingerprint' => Hasher::hash($value),
            ]
        );
    }

    /**
     * The write validator found no decoded tree for a command that writes the layout column. The field
     * serializer memoizes one for every such command before the validation event fires, so the absence is a
     * broken write-path invariant rather than a defect in the caller's payload: falling back to a second
     * decode here would validate a tree other than the one being stored, and do it silently.
     */
    public static function layoutWriteMemoMissing(string $entityName, string $writePath): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LAYOUT_WRITE_MEMO_MISSING,
            'No decoded layout tree was memoized for the "{{ entityName }}" write at "{{ writePath }}".',
            ['entityName' => $entityName, 'writePath' => $writePath]
        );
    }

    // The assignment-mismatch write violation, shared by the Core entity-assignment validator and the Storefront
    // header/footer validator: both reject a content-layout assignment whose bound layout's immutable root source
    // is a different page kind, with the identical ConstraintViolation shape. $assignmentType is the entity type
    // (Core) or the section id (Storefront); $propertyPath is the assignment's content_layout_id field path.
    public static function rootSourceAssignmentMismatchViolation(string $rootSource, string $assignmentType, string $propertyPath): ConstraintViolation
    {
        $exception = self::rootSourceAssignmentMismatch($rootSource, $assignmentType);

        return new ConstraintViolation(
            $exception->getMessage(),
            $exception->getMessage(),
            [],
            null,
            $propertyPath,
            $rootSource,
            null,
            $exception->getErrorCode(),
        );
    }

    // The layout column's write rejection, built the same way as the assignment-mismatch violation above and
    // wrapped for the DAL: the layout field serializer decodes the payload at the write boundary, and the
    // decode's defects are reported to the caller as a structured refusal of that payload rather than as an
    // unstructured 500. Most of them are the caller's input — an unknown key, a malformed container, a value
    // of the wrong type. Not all: CONFIG_SERIALIZER_NOT_REGISTERED, raised by DataLoaderConfigSerializerProvider
    // for a loader source with no registered serializer, signals a missing registration, an internal fault the
    // caller cannot have caused. It is reported here the same way regardless — it is already a CLIENT_DEFECT_CODE
    // and encode() has always wrapped it too, and telling the caller which loader source the tree named is more
    // use than a 500 either way. $defect is the decode failure, whose message and error code the violation
    // carries; $fieldKey is the written field, $value the payload it rejected, and $writePath the command's path
    // the DAL reports the violation under.
    public static function layoutWriteRejection(self $defect, string $fieldKey, mixed $value, string $writePath): WriteConstraintViolationException
    {
        $violation = new ConstraintViolation(
            $defect->getMessage(),
            $defect->getMessage(),
            [],
            null,
            '/' . $fieldKey,
            $value,
            null,
            $defect->getErrorCode(),
        );

        return new WriteConstraintViolationException(new ConstraintViolationList([$violation]), $writePath);
    }
}
