<?php

class Migrations_Migration705 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("DELETE FROM `s_core_config_elements` WHERE name = 'category_default_tpl';");

        $this->updateSnippets();
        $this->updateCustomTemplates();
        $this->emptyCustomTemplates();
    }

    private function updateSnippets()
    {
        $this->addSql('DELETE FROM `s_core_snippets` WHERE name = "view/settings_default_settings_template_help"');
        $this->addSql('UPDATE `s_core_snippets` SET `value` = "Listen Layout" WHERE `name` = "view/settings_default_settings_template_label" AND localeID = 1 AND dirty = 0;');
        $this->addSql('UPDATE `s_core_snippets` SET `value` = "Listing layout" WHERE `name` = "view/settings_default_settings_template_label" AND localeID = 2 AND dirty = 0;');
        $this->addSql('UPDATE `s_core_config_elements` SET label = "Verfügbare Listen Layouts" WHERE label = "Verfügbare Templates Kategorien";');
        $this->addSql('UPDATE `s_core_config_element_translations` SET label = "Available listing layouts" WHERE label = "Available template categories";');
    }

    private function updateCustomTemplates()
    {
        $templateBlacklist = [
            'article_listing_1col.tpl',
            'article_listing_2col.tpl',
            'article_listing_3col.tpl',
            'article_listing_4col.tpl'
        ];
        $templates = $this->connection->query("SELECT vals.id, vals.value FROM `s_core_config_values` as vals INNER JOIN `s_core_config_elements` as elems ON elems.id = vals.element_id WHERE elems.name = 'categorytemplates'")
            ->fetchAll(\PDO::FETCH_KEY_PAIR);

        foreach ($templates as $valueId => $serializedValue) {
            $cleanedTemplates = [];
            $templates = unserialize($serializedValue);

            foreach (explode(";", $templates) as $template) {
                list($file, $name) = explode(":", $template);

                if (in_array($file, $templateBlacklist) || empty($name)) {
                    continue;
                }

                $cleanedTemplates[] = $file . ":" . $name;
            }

            if (empty($cleanedTemplates)) {
                $this->connection->exec("DELETE FROM `s_core_config_values` WHERE id = " . $valueId);
            } else {
                $this->connection->prepare("UPDATE `s_core_config_values` SET `value` = :newTemplates WHERE id = :valueId")
                    ->execute([
                        ':newTemplates' => serialize(join(";", $cleanedTemplates)),
                        ':valueId' => $valueId
                    ]);
            }
        }

        $this->addSql("UPDATE `s_categories` SET template = NULL WHERE template IN ('" . join("','", $templateBlacklist) . "')");
    }

    private function emptyCustomTemplates()
    {
        $this->addSql("UPDATE `s_core_config_elements` SET `value` = 's:0:\"\";' WHERE `name` = 'categorytemplates'");
    }
}
