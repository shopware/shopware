<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class ProductExportValidator implements EventSubscriberInterface
{
    public const VIOLATION_DUPLICATE_FILENAME = 'write_product_export_duplicate_filename';

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $commands = $event->getCommands();
        $violations = new ConstraintViolationList();

        foreach ($commands as $command) {
            if ((!($command instanceof UpdateCommand) && !($command instanceof InsertCommand)) || $command->getDefinition()->getClass() !== ProductExportDefinition::class) {
                continue;
            }

            $pk = $command->getPrimaryKey();
            $payload = $command->getPayload();

            if (!isset($payload['file_name'])) {
                continue;
            }

            /** @var QueryBuilder $qb */
            $qb = $this->connection->createQueryBuilder();

            $query = $qb
                ->select('id')
                ->from('product_export')
                ->where($qb->expr()->eq('file_name', ':fileName'))
                ->andWhere($qb->expr()->neq('id', ':id'))
                ->setParameter(':fileName', $payload['file_name'])
                ->setParameter(':id', $pk['id'])
                ->setMaxResults(1);

            $productExports = $query->execute()->fetchAll();

            if (empty($productExports)) {
                continue;
            }

            $msgTpl = 'The file name {{ fileName }} is already in use.';
            $parameters = ['{{ fileName }}' => $payload['file_name']];
            $msg = 'The file name for product comparisons has to be unique.';
            $violation = new ConstraintViolation(
                $msg,
                $msgTpl,
                $parameters,
                null,
                null,
                $payload['file_name'],
                null,
                self::VIOLATION_DUPLICATE_FILENAME
            );

            $violations->add($violation);
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }
}
