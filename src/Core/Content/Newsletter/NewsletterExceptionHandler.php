<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter;

use Shopware\Core\Content\Newsletter\Exception\LanguageOfNewsletterDeleteException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;

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
        if (preg_match('/SQLSTATE\[23000\]:.*1451.*a foreign key constraint.*newsletter_recipient.*CONSTRAINT `fk.newsletter_recipient.language_id`/', $e->getMessage())) {
            return new LanguageOfNewsletterDeleteException('', $e);
        }

        return null;
    }
}
