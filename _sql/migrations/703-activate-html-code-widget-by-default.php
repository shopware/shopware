<?php
class Migrations_Migration703 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->renameHtmlElement();
        $this->addComponentToLibrary();
        $this->fetchComponentId();
        $this->addComponentFields();
    }

    private function renameHtmlElement()
    {
        $sql = <<<SQL
UPDATE `s_library_component` SET `name` = 'Text Element'
WHERE `x_type` = 'emotion-components-html-element' AND pluginID IS NULL
SQL;

        $this->addSql($sql);
    }

    private function addComponentToLibrary()
    {
        $sql = <<<SQL
INSERT INTO `s_library_component` (`name`, `x_type`, `convert_function`, `description`, `template`, `cls`, `pluginID`)
VALUES ('Code Element', 'emotion-components-html-code', NULL, '', 'component_html_code', 'html-code-element', null);
SQL;

        $this->addSql($sql);
    }

    private function fetchComponentId()
    {
        $sql = <<<SQL
SET @componentId = (
  SELECT id
  FROM s_library_component
  WHERE `x_type` LIKE "emotion-components-html-code"
  AND `template` LIKE "component_html_code"
  LIMIT 1
);
SQL;
        $this->addSql($sql);
    }

    private function addComponentFields()
    {
        $sql = <<<SQL
INSERT INTO `s_library_component_field` (`componentID`, `name`, `x_type`, `field_label`, `allow_blank`, `position`)
VALUES (@componentId, 'javascript', 'codemirrorfield', 'JavaScript Code', 1, 0),
(@componentId, 'smarty', 'codemirrorfield', 'HTML Code', 1, 1);
SQL;
        $this->addSql($sql);
    }
}
