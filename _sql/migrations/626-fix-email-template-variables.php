<?php
class Migrations_Migration626 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $query = $this->getConnection()->query('SELECT id, content, contentHTML FROM `s_core_config_mails` WHERE dirty = 0');
        $untouchedMails = $query->fetchAll();

        foreach ($untouchedMails as $mail) {
            $replacedContent = $this->replaceOldVarSyntax($mail['content']);
            $replacedContentHTML = $this->replaceOldVarSyntax($mail['contentHTML']);

            if ($replacedContent != $mail['content'] || $replacedContentHTML != $mail['contentHTML']) {
                $mailId = $mail['id'];

                $replacedContent = !empty($replacedContent) ? trim($this->getConnection()->quote($replacedContent), "'") : "";
                $replacedContentHTML = !empty($replacedContentHTML) ? trim($this->getConnection()->quote($replacedContentHTML), "'") : "";

                $sql = <<<EOL
                    UPDATE
                        `s_core_config_mails`
                    SET
                        content="$replacedContent",
                        contentHTML="$replacedContentHTML"
                    WHERE
                        id = $mailId;
EOL;

                $this->addSql($sql);
            }
        }
    }

    private function replaceOldVarSyntax($content)
    {
        preg_match_all("/\{([a-z0-9\.\s]+)\}/i", $content, $matches);

        foreach ($matches[1] as $match) {
            if (empty($match) || $match === 'else') {
                continue;
            }

            $content = str_replace("{" . $match . "}", "{\$" . $match . "}", $content);
        }

        return trim($content);
    }
}
