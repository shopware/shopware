<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter;

use Shopware\Core\Content\Newsletter\Exception\LanguageOfNewsletterDeleteException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;

class NewsletterExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    /**
     * @internal (flag:FEATURE_NEXT_16640) - second parameter WriteCommand $command will be removed
     */
    public function matchException(\Exception $e, ?WriteCommand $command = null): ?\Exception
    {
        if ($e->getCode() !== 0) {
            return null;
        }
        if (!Feature::isActive('FEATURE_NEXT_16640') && $command->getDefinition()->getEntityName() !== LanguageDefinition::ENTITY_NAME) {
            return null;
        }

        if (preg_match('/SQLSTATE\[23000\]:.*1451.*a foreign key constraint.*newsletter_recipient.*CONSTRAINT `fk.newsletter_recipient.language_id`/', $e->getMessage())) {
            $languageId = '';
            if (!Feature::isActive('FEATURE_NEXT_16640')) {
                $primaryKey = $command->getPrimaryKey();
                $languageId = isset($primaryKey['id']) ? Uuid::fromBytesToHex($primaryKey['id']) : '';
            }

            return new LanguageOfNewsletterDeleteException($languageId, $e);
        }

        return null;
    }
}
