<?php

class Migrations_Migration747 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $statement = $this->connection->query("SELECT * FROM s_emotion_attributes LIMIT 1");
        $attributes = $statement->fetch(PDO::FETCH_ASSOC);

        if (empty($attributes)) {
            return;
        }

        if (!array_key_exists('swag_mode', $attributes)) {
            return;
        }

        $sql = <<<EOD
UPDATE s_emotion AS emotion
INNER JOIN s_emotion_attributes AS attributes
    ON emotion.id = attributes.emotionID
SET emotion.mode = attributes.swag_mode
WHERE attributes.swag_mode = 'storytelling'
EOD;

        $this->addSql($sql);
    }
}
