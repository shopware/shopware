<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;

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
     * @param array<string, string> $externalNamespaces map of class name => namespace for DTOs
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

        $useStatements = $this->collectUseStatements($definition, $namespace, $externalNamespaces);
        if ($useStatements !== []) {
            sort($useStatements);
            foreach ($useStatements as $useStatement) {
                $lines[] = 'use ' . $useStatement . ';';
            }
            $lines[] = '';
        }

        if ($definition->description !== null) {
            $lines = [...$lines, ...$this->renderDescription($definition->description)];
        }

        if ($definition->package !== null) {
            $lines[] = '#[Package(\'' . $definition->package . '\')]';
        }

        $lines[] = 'final readonly class ' . $definition->name;
        $lines[] = '{';
        $lines[] = '    public function __construct(';

        $propertyBlocks = array_map(
            fn (OpenApiDtoProperty $property): string => $this->renderConstructorProperty($property),
            $this->sortProperties($definition->properties),
        );

        if ($propertyBlocks !== []) {
            $lines[] = implode("\n", $propertyBlocks);
        }

        $lines[] = '    ) {';
        $lines[] = '    }';
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
            '        public %s%s $%s%s,',
            $this->phpTypeAllowsNullablePrefix($property->phpType) && $this->isEffectivelyNullable($property) ? '?' : '',
            $property->phpType,
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
    private function renderDescription(string $description): array
    {
        $lines = ['/**'];
        foreach (explode("\n", $description) as $line) {
            $lines[] = ' * ' . $this->escapePhpDoc($line);
        }
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
                fn (string $case): string => '\'' . $this->escapePhpSingleQuoted($case) . '\'',
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
        if ($property->phpType !== OpenApiDtoSchemaParser::PHP_TYPE_ARRAY || $property->arrayItemType === null || !$this->isPrimitive($property->arrayItemType)) {
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
        if ($property->phpType === OpenApiDtoSchemaParser::PHP_TYPE_ARRAY && $property->arrayItemType !== null) {
            return !$this->isPrimitive($property->arrayItemType);
        }

        return !$this->isPrimitive($property->phpType);
    }

    private function renderDefault(OpenApiDtoProperty $property): string
    {
        if ($property->hasDefaultValue) {
            return ' = ' . $this->formatDefaultValue($property->defaultValue);
        }

        if (!$property->required) {
            return ' = null';
        }

        return '';
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

    /**
     * @param array<string, string> $externalNamespaces
     *
     * @return list<string>
     */
    private function collectUseStatements(OpenApiDtoDefinition $definition, ?string $namespace, array $externalNamespaces): array
    {
        $current = $namespace !== null ? trim($namespace, '\\') : '';

        $imports = [];
        if ($this->needsDateTimeFormatAssertion($definition)) {
            $imports['Shopware\Core\Defaults'] = true;
        }
        if ($this->needsAssertImport($definition)) {
            $imports['Symfony\Component\Validator\Constraints as Assert'] = true;
        }
        if ($definition->package !== null) {
            $imports['Shopware\Core\Framework\Log\Package'] = true;
        }

        foreach ($definition->properties as $property) {
            foreach ([$property->phpType, $property->arrayItemType] as $type) {
                if ($type === null || $this->isPrimitive($type)) {
                    continue;
                }

                $targetNamespace = $externalNamespaces[$type] ?? null;
                if ($targetNamespace === null) {
                    continue;
                }

                $targetNamespace = trim($targetNamespace, '\\');
                if ($targetNamespace === $current) {
                    continue;
                }

                $imports[$targetNamespace . '\\' . $type] = true;
            }
        }

        return array_keys($imports);
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

    private function isPrimitive(string $type): bool
    {
        return \in_array($type, OpenApiDtoSchemaParser::PHP_TYPES, true);
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
