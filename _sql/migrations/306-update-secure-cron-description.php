<?php
class Migrations_Migration306 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $statement = $this->getConnection()->prepare(
            "SELECT * FROM s_core_plugins WHERE name = 'Cron' AND installation_date IS NOT NULL"
        );

        $statement->execute();
        $data = $statement->fetchAll();

        if (!empty($data)) {
            $sql = <<<'EOD'
                SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'CronSecurity' LIMIT 1);

                SET @cronSecureByAccountId = (SELECT id FROM s_core_config_elements WHERE form_id = @formId AND `name` = 'cronSecureByAccount');

                UPDATE `s_core_config_elements`
                SET `description` = 'Es werden nur Anfragen von authentifizierten Backend Benutzern akzeptiert'
                WHERE id = @cronSecureByAccountId
                AND `description` = 'Es werden nur Anfragen von authentifizierten Administratoren akzeptieren';

                UPDATE `s_core_config_element_translations`
                SET `description` = 'If set, requests received from authenticated backend users will be accepted'
                WHERE element_id = @cronSecureByAccountId
                AND `description` = 'If set, requests received from authenticated admin users will be accepted';
EOD;

            $this->addSql($sql);
        }
    }
}
