<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;

class PlantUmlErdDumper implements ErdDumper
{
    private const TEMPLATE = <<<EOD
@startuml
' uncomment the line below if you're using computer with a retina display
' skinparam dpi 300
!define Table(name,desc) class name as "desc" << (T,#FFAAAA) >>
!define ForeignTable(name,desc) class name as "desc" << (T,#ada6a6) >>
!define TranslationTable(name,desc) class name as "desc" << (I,#4286f4) >>
' we use bold for primary key
' green color for unique
' and underscore for not_null
!define primary_key(x) <b>x</b>
!define unique(x) <color:green>x</color>
!define not_null(x) <u>x</u>
' other tags available:
' <i></i>
' <back:COLOR></color>, where color is a color name or html color code
' (#FFAACC)
' see: http://plantuml.com/classes.html#More
hide methods
hide stereotypes
hide empty members
skinparam backgroundColor #FFFFFF

' entities

%s

' relationshipd

%s
@enduml

EOD;

    private const TEMPLATE_TABLE = <<<EOD
Table(%s, "%s\\n%s") {
   %s
}
EOD;

    private const TEMPLATE_FOREIGN_TABLE = <<<EOD
ForeignTable(%s, "%s") {
}
EOD;

    private const TEMPLATE_TRANSLATION_TABLE = <<<EOD
TranslationTable(%s, "%s\\n(%s)") {
   %s
}
EOD;

    /**
     * @var array
     */
    private $tables = [];

    /**
     * @var array
     */
    private $associations = [];

    /**
     * @var array
     */
    private $foreignTables = [];

    public function addTable(string $definition, string $entityName, string $description, bool $isTranslation): void
    {
        if ($description !== '') {
            $description = '(' . $description . ')';
        }

        $this->tables[$definition] = [
            $definition,
            $entityName,
            $description,
            [],
            $isTranslation,
        ];
    }

    public function addField(string $definition, Field $field, string $type): void
    {
        $rendered = $field->getPropertyName();

        if ($field->is(PrimaryKey::class)) {
            $rendered = 'primary_key(' . $rendered . ')';
        } elseif ($field->is(Required::class)) {
            $rendered = 'not_null(' . $rendered . ')';
        }

        $rendered .= " $type";

        $this->tables[$definition][3][] = $rendered;
    }

    public function dump(): string
    {
        $tables = [];
        foreach ($this->tables as $table) {
            $tables[] = $this->renderTable($table);
        }

        foreach ($this->foreignTables as $definition => $name) {
            if (isset($this->tables[$definition])) {
                continue;
            }

            $tables[] = $this->renderForeignTable($definition, $name);
        }

        return sprintf(
            self::TEMPLATE,
            implode(\PHP_EOL, $tables),
            implode(\PHP_EOL, $this->associations)
        );
    }

    public function addAssociation(
        string $definition,
        string $name,
        string $referenceDefinition,
        string $referenceName
    ): void {
        $association = "$definition --> $referenceDefinition";

        $keys = [$definition, $referenceDefinition];
        sort($keys);
        $hash = md5(implode('', $keys));

        $this->foreignTables[$definition] = $name;
        $this->foreignTables[$referenceDefinition] = $referenceName;

        $this->associations[$hash] = $association;
    }

    private function renderTable(array $table): string
    {
        $table[3] = implode(\PHP_EOL . '   ', $table[3]);
        $isTranslation = $table[4];

        if ($isTranslation) {
            return sprintf(
                self::TEMPLATE_TRANSLATION_TABLE,
                ...$table
            );
        }

        return sprintf(
            self::TEMPLATE_TABLE,
            ...$table
        );
    }

    private function renderForeignTable(string $id, string $name): string
    {
        return sprintf(
            self::TEMPLATE_FOREIGN_TABLE,
            $id,
            $name
        );
    }
}
