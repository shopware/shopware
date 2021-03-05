<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

/**
 * Adds a missing unique constraint to column `technical_name` of table `document_type`.
 * Before that, it removes rows with duplicated `technical_name` from table `document_type`
 */
/**
 * @deprecated tag:v6.5.0 Will be deleted. Migrations are now namespaced by major version
 */
class Migration1572273565AddUniqueConstraintToTechnicalNameOfDocumentType extends \Shopware\Core\Migration\V6_3\Migration1572273565AddUniqueConstraintToTechnicalNameOfDocumentType
{
}
