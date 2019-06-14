<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

class MarkdownErdDumper implements ErdDumper
{
    private const TEMPLATE_HEAD = <<<EOD
[titleEn]: <>(%s)

[Back to modules](./../10-modules.md)

%s

![%s](./%s)

%s

[Back to modules](./../10-modules.md)

EOD;

    private const TEMPLATE_TABLE = <<<EOD

### Table `%s`

%s

EOD;

    private $tables = [];

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $overviewImage;

    public function __construct(
        string $title,
        string $description,
        string $overviewImage
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->overviewImage = $overviewImage;
    }

    public function addTable(string $definition, string $entityName, string $description, bool $isTranslation): void
    {
        if ($description === '') {
            return;
        }

        $this->tables[] = sprintf(self::TEMPLATE_TABLE, $entityName, $description);
    }

    public function addField(string $definition, Field $field, string $type): void
    {
        // ignore
    }

    public function dump(): string
    {
        return sprintf(
            self::TEMPLATE_HEAD,
            $this->title,
            $this->description,
            $this->title,
            $this->overviewImage,
            implode(PHP_EOL, $this->tables)
        );
    }

    public function addAssociation(
        string $definition,
        string $name,
        string $referenceDefinition,
        string $referenceName
    ): void {
        // ignore
    }
}
