<?php

class Migrations_Migration735 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $statement = $this->connection->query("SELECT id, category_id FROM s_core_shops");
        $shopCategories = $statement->fetchAll(PDO::FETCH_KEY_PAIR);

        $sql = <<<EOD
INSERT IGNORE INTO s_emotion_shops (shop_id, emotion_id)
SELECT :shopId as shop_id,
       ec.emotion_id as emotion_id
FROM s_emotion_categories ec
    INNER JOIN s_emotion e
        ON e.id = ec.emotion_id
        AND e.is_landingpage = 1
    INNER JOIN s_categories c
        ON c.id = ec.category_id
        AND (c.path LIKE :path OR c.id = :categoryId)

EOD;

        $statement = $this->connection->prepare($sql);

        foreach ($shopCategories as $shopId => $category) {
            $path = '%|'.$category.'|%';
            $statement->execute([':path' => $path, ':categoryId' => $category, ':shopId' => $shopId]);
        }
    }
}
