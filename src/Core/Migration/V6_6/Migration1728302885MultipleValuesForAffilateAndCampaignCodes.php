<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleIndexer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1728302885MultipleValuesForAffilateAndCampaignCodes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1728302885;
    }

    public function update(Connection $connection): void
    {
        $ruleIds = $connection->fetchFirstColumn(<<<'SQL'
            SELECT DISTINCT
                `rule_id`
            FROM
                `rule_condition`
            WHERE `type` IN('orderAffiliateCode', 'customerAffiliateCode', 'orderCampaignCode', 'customerCampaignCode')
            SQL);

        if (\count($ruleIds) === 0) {
            return;
        }

        $this->convertAffilateCodeRules($connection);
        $this->convertCampaignCodeRules($connection);

        // rebuild payload of rules (as it contains the conditions serialized)
        $connection->executeStatement('UPDATE rule SET payload = NULL WHERE id in (:rule_ids)', [
            'rule_ids' => $ruleIds,
        ], [
            'rule_ids' => ArrayParameterType::BINARY,
        ]);

        $this->registerIndexer($connection, 'rule.indexer', [RuleIndexer::PAYLOAD_UPDATER]);
    }

    private function convertAffilateCodeRules(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            UPDATE
                `rule_condition`
            SET
                `value` = JSON_SET(
                    `value`,
                    '$.affiliateCode',
                    JSON_ARRAY(JSON_EXTRACT(`value`, '$.affiliateCode'))
                )
            WHERE
                `type` IN('orderAffiliateCode', 'customerAffiliateCode')
                AND JSON_VALID(`value`)
                AND JSON_TYPE(JSON_EXTRACT(`value`, '$.affiliateCode')) = 'STRING';
            SQL);
    }

    private function convertCampaignCodeRules(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            UPDATE
                `rule_condition`
            SET
                `value` = JSON_SET(
                    `value`,
                    '$.campaignCode',
                    JSON_ARRAY(JSON_EXTRACT(`value`, '$.campaignCode'))
                )
            WHERE
                `type` IN('orderCampaignCode', 'customerCampaignCode')
                AND JSON_VALID(`value`)
                AND JSON_TYPE(JSON_EXTRACT(`value`, '$.campaignCode')) = 'STRING';
            SQL);
    }
}
