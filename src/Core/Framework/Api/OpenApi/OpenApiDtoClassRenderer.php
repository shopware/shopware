<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;

/**
 * @internal
 */
#[Package('framework')]
final class OpenApiDtoClassRenderer
{
    /**
     * @var array<string, string>
     */
    private const FORMAT_ASSERTIONS = [
        'date' => '#[Assert\Date]',
        'date-time' => '#[Assert\DateTime(format: Defaults::STORAGE_DATE_TIME_FORMAT)]',
        'email' => '#[Assert\Email]',
        'uri' => '#[Assert\Url]',
        'uuid' => '#[Assert\Uuid]',
    ];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, string> $externalNamespaces map of FQCN => namespace for DTOs
     *                                                  that live in a different namespace and must be imported
     */
    public function renderClass(OpenApiDtoDefinition $definition, string $namespace, array $externalNamespaces = []): string
    {
        $lines = [
            '<?php declare(strict_types=1);',
            '',
            '/**',
            ' * This file is auto-generated.',
            ' * Do not edit manually.',
            ' *',
            ' * Last generated: ' . $this->clock->now()->format('Y-m-d H:i:s'),
            ' */',
            '',
        ];

        if ($namespace === '') {
            throw FrameworkException::invalidArgumentException('Namespace cannot be empty.');
        }

        $lines[] = 'namespace ' . trim($namespace, '\\') . ';';
        $lines[] = '';

        if ($definition->enumValues !== []) {
            return $this->renderEnum($definition, $lines);
        }

        $useStatements = $this->collectUseStatements($definition, $namespace, $externalNamespaces);
        if ($useStatements !== []) {
            sort($useStatements);
            foreach ($useStatements as $useStatement) {
                $lines[] = 'use ' . $useStatement . ';';
            }
            $lines[] = '';
        }

        $lines = [...$lines, ...$this->renderDescription($definition->description)];

        if ($definition->package !== null) {
            $lines[] = '#[Package(\'' . $definition->package . '\')]';
        }

        $lines[] = '#[JsonStreamable]';

        $baseClass = $this->baseClass($definition);
        $classDeclaration = 'final class ' . $this->shortClassName($definition->name) . ' extends ' . $this->shortClassName($baseClass);
        $lines[] = $classDeclaration;
        $lines[] = '{';
        $lines[] = '    /**';
        $lines[] = '     * @internal';
        $lines[] = '     */';
        $lines[] = '    public function __construct(';

        $propertyBlocks = array_map(
            fn (OpenApiDtoProperty $property): string => $this->renderConstructorProperty($property),
            $this->sortProperties($definition->properties),
        );

        if ($propertyBlocks !== []) {
            $lines[] = implode("\n", $propertyBlocks);
        }

        $lines[] = '    ) {';
        if ($definition->type === OpenApiDtoType::Response && $definition->responseStatusCode !== Response::HTTP_OK) {
            $lines[] = '        parent::__construct(statusCode: ' . $this->responseStatusConstant($definition->responseStatusCode) . ');';
        }
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $lines
     */
    private function renderEnum(OpenApiDtoDefinition $definition, array $lines): string
    {
        $lines[] = 'use ' . Package::class . ';';
        $lines[] = '';
        $lines = [...$lines, ...$this->renderDescription($definition->description)];
        if ($definition->package !== null) {
            $lines[] = '#[Package(\'' . $definition->package . '\')]';
        }
        $lines[] = 'enum ' . $this->shortClassName($definition->name) . ': ' . ($definition->enumType ?? 'string');
        $lines[] = '{';
        $usedCaseNames = [];
        foreach ($definition->enumValues as $value) {
            $caseName = $this->enumCaseName($value, $definition->name);

            if (isset($usedCaseNames[$caseName])) {
                throw FrameworkException::invalidArgumentException(
                    \sprintf('Enum values in "%s" produce duplicate PHP case name "%s".', $definition->name, $caseName),
                );
            }
            $usedCaseNames[$caseName] = true;

            $formattedValue = \is_int($value) ? (string) $value : '\'' . $this->escapePhpSingleQuoted((string) $value) . '\'';
            $lines[] = '    case ' . $caseName . ' = ' . $formattedValue . ';';
        }
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param list<OpenApiDtoProperty> $properties
     *
     * @return list<OpenApiDtoProperty>
     */
    private function sortProperties(array $properties): array
    {
        usort(
            $properties,
            fn (OpenApiDtoProperty $a, OpenApiDtoProperty $b): int => (int) $this->hasParameterDefault($a) <=> (int) $this->hasParameterDefault($b),
        );

        return $properties;
    }

    private function renderConstructorProperty(OpenApiDtoProperty $property): string
    {
        $lines = [];
        $phpDoc = $this->renderPropertyPhpDoc($property);
        if ($phpDoc !== []) {
            $lines = [...$lines, ...$phpDoc];
        }

        $constraints = $this->renderConstraints($property);
        if ($constraints !== []) {
            $lines = [...$lines, ...$constraints];
        }

        $lines[] = \sprintf(
            '        public %s $%s%s,',
            $this->renderPhpType($property),
            $property->name,
            $this->renderDefault($property),
        );

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function renderPropertyPhpDoc(OpenApiDtoProperty $property): array
    {
        $lines = [];

        if ($property->unresolvedReference) {
            $lines[] = '        /**';
            if ($property->description !== null) {
                $lines[] = '         * ' . $this->escapePhpDoc($property->description);
                $lines[] = '         *';
            }
            $lines[] = '         * @var array<string, mixed>';
            $lines[] = '         *';
            // TODO: Generate a dedicated DTO for this reference once we also generate static
            // schema files for generic entity definitions (currently produced at runtime by the DAL).
            $lines[] = '         * @todo Replace with the generated DTO once static schema files exist for generic entity definitions.';
            $lines[] = '         */';

            return $lines;
        }

        if ($property->phpType === OpenApiDtoSchemaParser::PHP_TYPE_ARRAY && $property->arrayMapValueType !== null) {
            $lines[] = '        /**';
            if ($property->description !== null) {
                $lines[] = '         * ' . $this->escapePhpDoc($property->description);
                $lines[] = '         *';
            }
            $lines[] = '         * @var array<string, ' . $property->arrayMapValueType . '>';
            $lines[] = '         */';

            return $lines;
        }

        if ($property->phpType === OpenApiDtoSchemaParser::PHP_TYPE_ARRAY && $property->arrayItemType === null) {
            $lines[] = '        /**';
            if ($property->description !== null) {
                $lines[] = '         * ' . $this->escapePhpDoc($property->description);
                $lines[] = '         *';
            }
            $lines[] = '         * @var array<string, mixed>';
            $lines[] = '         */';

            return $lines;
        }

        if ($property->phpType === OpenApiDtoSchemaParser::PHP_TYPE_ARRAY && $property->arrayItemType !== null) {
            $lines[] = '        /**';
            $lines[] = '         * @var list<' . $property->arrayItemType . '>' . ($property->description !== null ? ' ' . $this->escapePhpDoc($property->description) : '');
            $lines[] = '         */';

            return $lines;
        }

        if ($property->description !== null) {
            $lines[] = '        /**';
            $lines[] = '         * ' . $this->escapePhpDoc($property->description);
            $lines[] = '         */';
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderDescription(?string $description): array
    {
        $lines = ['/**'];
        if ($description !== null) {
            foreach (explode("\n", $description) as $line) {
                $lines[] = ' * ' . $this->escapePhpDoc($line);
            }
            $lines[] = ' *';
        }

        $lines[] = ' * @codeCoverageIgnore';
        $lines[] = ' */';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderConstraints(OpenApiDtoProperty $property): array
    {
        $constraints = [];

        if ($property->required && !$property->nullable) {
            $constraints[] = $property->phpType === 'string' ? '        #[Assert\NotBlank]' : '        #[Assert\NotNull]';
        }

        if ($property->format !== null && isset(self::FORMAT_ASSERTIONS[$property->format])) {
            $constraints[] = '        ' . self::FORMAT_ASSERTIONS[$property->format];
        }

        if ($property->pattern !== null) {
            $constraints[] = \sprintf(
                "        #[Assert\Regex(pattern: '%s')]",
                $this->escapePhpSingleQuoted('~' . str_replace('~', '\~', $property->pattern) . '~'),
            );
        }

        if ($property->enum !== null) {
            $choices = implode(', ', array_map(
                fn (string|int|float|bool $case): string => $this->formatDefaultValue($case),
                $property->enum,
            ));
            $constraints[] = '        #[Assert\Choice(choices: [' . $choices . '])]';
        }

        if ($property->phpType === 'string' && $property->minLength !== null) {
            $constraints[] = '        #[Assert\Length(min: ' . $property->minLength . ')]';
        }

        if ($property->phpType === OpenApiDtoSchemaParser::PHP_TYPE_ARRAY && $property->minItems !== null) {
            $constraints[] = '        #[Assert\Count(min: ' . $property->minItems . ')]';
        }

        $arrayItemConstraints = $this->renderArrayItemConstraints($property);
        if ($arrayItemConstraints !== null) {
            $constraints[] = '        #[Assert\All(' . $arrayItemConstraints . ')]';
        }

        if ($this->needsValidConstraint($property)) {
            $constraints[] = '        #[Assert\Valid]';
        }

        return $constraints;
    }

    private function renderArrayItemConstraints(OpenApiDtoProperty $property): ?string
    {
        if ($property->phpType !== OpenApiDtoSchemaParser::PHP_TYPE_ARRAY || $property->arrayItemType === null || !\in_array($property->arrayItemType, OpenApiDtoSchemaParser::PHP_TYPES, true)) {
            return null;
        }

        $constraints = ['new Assert\Type(\'' . $this->escapePhpSingleQuoted($property->arrayItemType) . '\')'];

        if ($property->arrayItemType === 'string' && $property->arrayItemMinLength !== null) {
            $constraints[] = 'new Assert\Length(min: ' . $property->arrayItemMinLength . ')';
        }

        if (\count($constraints) === 1) {
            return $constraints[0];
        }

        return '[' . implode(', ', $constraints) . ']';
    }

    private function needsValidConstraint(OpenApiDtoProperty $property): bool
    {
        if ($property->nativeEnum) {
            return false;
        }

        if ($property->phpType === OpenApiDtoSchemaParser::PHP_TYPE_ARRAY && $property->arrayMapValueType !== null) {
            return $this->containsDtoType($property->arrayMapValueType);
        }

        if ($property->phpType === OpenApiDtoSchemaParser::PHP_TYPE_ARRAY && $property->arrayItemType !== null) {
            return !$this->isPrimitive($property->arrayItemType);
        }

        return !$this->isPrimitive($property->phpType);
    }

    private function renderDefault(OpenApiDtoProperty $property): string
    {
        if ($property->hasDefaultValue) {
            if ($property->nativeEnum) {
                return ' = ' . $property->phpType . '::' . $this->enumCaseName($property->defaultValue, $property->phpType);
            }

            return ' = ' . $this->formatDefaultValue($property->defaultValue);
        }

        if (!$property->required) {
            return ' = null';
        }

        return '';
    }

    private function enumCaseName(string|int|float|bool|null $value, string $enumName): string
    {
        $caseName = strtoupper((string) preg_replace('/(?<!^)[A-Z]/', '_$0', (string) $value));
        $caseName = (string) preg_replace('/[^A-Z0-9_]/', '_', $caseName);
        if ($caseName === '') {
            throw FrameworkException::invalidArgumentException(
                \sprintf('Enum value in "%s" cannot be converted to a valid PHP case name.', $enumName),
            );
        }
        if (preg_match('/^[0-9]/', $caseName) === 1) {
            $caseName = '_' . $caseName;
        }

        return $caseName;
    }

    private function formatDefaultValue(string|int|float|bool|null $value): string
    {
        if (\is_string($value)) {
            return '\'' . $this->escapePhpSingleQuoted($value) . '\'';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function hasParameterDefault(OpenApiDtoProperty $property): bool
    {
        return !$property->required || $property->hasDefaultValue;
    }

    private function isEffectivelyNullable(OpenApiDtoProperty $property): bool
    {
        if ($property->nullable) {
            return true;
        }

        if ($property->hasDefaultValue) {
            return false;
        }

        return !$property->required;
    }

    private function phpTypeAllowsNullablePrefix(string $type): bool
    {
        return $type !== 'mixed';
    }

    private function renderPhpType(OpenApiDtoProperty $property): string
    {
        if (!$this->isEffectivelyNullable($property) || !$this->phpTypeAllowsNullablePrefix($property->phpType)) {
            return $property->phpType;
        }

        if (str_contains($property->phpType, '|')) {
            return $property->phpType . '|null';
        }

        return '?' . $property->phpType;
    }

    /**
     * @param array<string, string> $externalNamespaces map of FQCN => namespace
     *
     * @return list<string>
     */
    private function collectUseStatements(OpenApiDtoDefinition $definition, ?string $namespace, array $externalNamespaces): array
    {
        $current = $namespace !== null ? trim($namespace, '\\') : '';

        $imports = [];
        $baseClass = $this->baseClass($definition);
        $baseClassNamespace = substr($baseClass, 0, (int) strrpos($baseClass, '\\'));
        if ($baseClassNamespace !== $current) {
            $imports[$baseClass] = true;
        }
        if ($this->needsDateTimeFormatAssertion($definition)) {
            $imports[Defaults::class] = true;
        }
        if ($this->needsAssertImport($definition)) {
            $imports['Symfony\Component\Validator\Constraints as Assert'] = true;
        }
        if ($definition->package !== null) {
            $imports[Package::class] = true;
        }
        if ($definition->type === OpenApiDtoType::Response && $definition->responseStatusCode !== Response::HTTP_OK) {
            $imports[Response::class] = true;
        }
        $imports[JsonStreamable::class] = true;

        foreach ($definition->properties as $property) {
            foreach ([$property->phpType, $property->arrayItemType, $property->arrayMapValueType] as $type) {
                if ($type === null) {
                    continue;
                }

                foreach ($this->typeNames($type) as $singleType) {
                    if ($this->isPrimitive($singleType)) {
                        continue;
                    }

                    $targetNamespace = $this->externalNamespaceForType($singleType, $current, $externalNamespaces);
                    if ($targetNamespace === null) {
                        continue;
                    }

                    $targetNamespace = trim($targetNamespace, '\\');
                    if ($targetNamespace === $current) {
                        continue;
                    }

                    $imports[$targetNamespace . '\\' . $singleType] = true;
                }
            }
        }

        return array_keys($imports);
    }

    /**
     * @param array<string, string> $externalNamespaces map of FQCN => namespace
     */
    private function externalNamespaceForType(string $type, string $currentNamespace, array $externalNamespaces): ?string
    {
        $matches = [];
        foreach ($externalNamespaces as $fqcn => $namespace) {
            if (substr($fqcn, (int) strrpos($fqcn, '\\') + 1) !== $type) {
                continue;
            }

            if (trim($namespace, '\\') === $currentNamespace) {
                continue;
            }

            $matches[$fqcn] = $namespace;
        }

        if (\count($matches) > 1) {
            throw FrameworkException::invalidArgumentException(\sprintf(
                'DTO type "%s" is ambiguous. Matching classes: %s.',
                $type,
                implode(', ', array_keys($matches)),
            ));
        }

        return $matches === [] ? null : (string) array_values($matches)[0];
    }

    private function responseStatusConstant(int $statusCode): string
    {
        return match ($statusCode) {
            Response::HTTP_OK => 'Response::HTTP_OK',
            Response::HTTP_CREATED => 'Response::HTTP_CREATED',
            Response::HTTP_ACCEPTED => 'Response::HTTP_ACCEPTED',
            Response::HTTP_NON_AUTHORITATIVE_INFORMATION => 'Response::HTTP_NON_AUTHORITATIVE_INFORMATION',
            Response::HTTP_NO_CONTENT => 'Response::HTTP_NO_CONTENT',
            Response::HTTP_RESET_CONTENT => 'Response::HTTP_RESET_CONTENT',
            Response::HTTP_PARTIAL_CONTENT => 'Response::HTTP_PARTIAL_CONTENT',
            default => (string) $statusCode,
        };
    }

    private function needsAssertImport(OpenApiDtoDefinition $definition): bool
    {
        foreach ($definition->properties as $property) {
            if ($this->renderConstraints($property) !== []) {
                return true;
            }
        }

        return false;
    }

    private function needsDateTimeFormatAssertion(OpenApiDtoDefinition $definition): bool
    {
        foreach ($definition->properties as $property) {
            if ($property->format === 'date-time') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return class-string<AbstractDto>
     */
    private function baseClass(OpenApiDtoDefinition $definition): string
    {
        return match ($definition->type) {
            OpenApiDtoType::Request => AbstractRequest::class,
            OpenApiDtoType::Response => AbstractResponse::class,
            OpenApiDtoType::Nested => AbstractDto::class,
        };
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return (string) end($parts);
    }

    private function isPrimitive(string $type): bool
    {
        foreach (explode('|', $type) as $singleType) {
            if (!\in_array($singleType, OpenApiDtoSchemaParser::PHP_TYPES, true)) {
                return false;
            }
        }

        return true;
    }

    private function containsDtoType(string $type): bool
    {
        foreach ($this->typeNames($type) as $typeName) {
            if (!$this->isPrimitive($typeName) && $typeName !== 'list' && $typeName !== 'null') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function typeNames(string $type): array
    {
        $typeNames = preg_split('/[^a-zA-Z0-9_\\\\]+/', $type, flags: \PREG_SPLIT_NO_EMPTY);
        \assert(\is_array($typeNames));

        return array_values(array_unique($typeNames));
    }

    private function escapePhpDoc(string $text): string
    {
        return str_replace('*/', '* /', $text);
    }

    private function escapePhpSingleQuoted(string $text): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $text);
    }
}
