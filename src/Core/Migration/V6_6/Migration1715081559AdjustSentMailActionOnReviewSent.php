<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1715081559AdjustSentMailActionOnReviewSent extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1715081559;
    }

    public function update(Connection $connection): void
    {
        $mailTemplateIds = $connection->createQueryBuilder()
            ->select('LOWER(HEX(mt.id))')
            ->from('mail_template', 'mt')
            ->innerJoin('mt', 'mail_template_type', 'mtt', 'mt.mail_template_type_id = mtt.id')
            ->where('mtt.technical_name = "review_form"')
            ->fetchFirstColumn();

        $flowIds = $connection->createQueryBuilder()
            ->select('fs.flow_id')
            ->from('flow_sequence', 'fs')
            ->innerJoin('fs', 'flow', 'f', 'f.id = fs.flow_id')
            ->where('f.event_name = "review_form.send"')
            ->fetchFirstColumn();

        $connection->createQueryBuilder()
            ->update('flow_sequence', 'fs')
            ->set('fs.config', 'JSON_SET(fs.config, "$.recipient.type", "admin")')
            ->from('flow_sequence', 'fs')
            ->where('fs.action_name = "action.mail.send"')
            ->andWhere('JSON_EXTRACT(fs.config, "$.recipient.type") = "default"')
            ->andWhere('JSON_EXTRACT(fs.config, "$.mailTemplateId") IN (:mailTemplateIds)')
            ->andWhere('fs.flow_id IN (:flowIds)')
            ->setParameter(
                'mailTemplateIds',
                $mailTemplateIds,
                ArrayParameterType::STRING
            )
            ->setParameter(
                'flowIds',
                $flowIds,
                ArrayParameterType::STRING
            )
            ->executeStatement();
    }
}
