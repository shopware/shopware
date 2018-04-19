<?php
class Migrations_Migration308 Extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * Increase the lenght of all fields that contain the session id
     * to allow usage of secure/long session hashes like session.hash_function="sha512"
     */
    public function up($modus)
    {
        $sql = <<<'EOD'
ALTER TABLE s_core_sessions_backend MODIFY id VARCHAR(128);
ALTER TABLE s_core_sessions MODIFY id VARCHAR(128);
ALTER TABLE s_core_auth MODIFY sessionID VARCHAR(128);
ALTER TABLE s_user MODIFY sessionID VARCHAR(128);
ALTER TABLE s_emarketing_lastarticles MODIFY sessionID VARCHAR(128);
ALTER TABLE s_order_basket MODIFY sessionID VARCHAR(128);
ALTER TABLE s_order_comparisons MODIFY sessionID VARCHAR(128);
EOD;
        $this->addSql($sql);
    }
}
