<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage;

use Shopware\Core\Content\LandingPage\Aggregate\LandingPageSalesChannel\LandingPageSalesChannelDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('content')]
class LandingPageValidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PostWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PostWriteValidationEvent $event): void
    {
        $writeException = $event->getExceptions();
        $commands = $event->getCommands();
        $violationList = new ConstraintViolationList();

        foreach ($commands as $command) {
            if (!($command instanceof InsertCommand) || $command->getDefinition()->getClass() !== LandingPageDefinition::class) {
                continue;
            }

            if (!$this->hasAnotherValidCommand($commands, $command)) {
                $violationList->addAll(
                    $this->validator->startContext()
                        ->atPath($command->getPath() . '/salesChannels')
                        ->validate(null, [new NotBlank()])
                        ->getViolations()
                );
                $writeException->add(new WriteConstraintViolationException($violationList));
            }
        }
    }

    /**
     * @param WriteCommand[] $commands
     */
    private function hasAnotherValidCommand(array $commands, WriteCommand $command): bool
    {
        $isValid = false;
        foreach ($commands as $searchCommand) {
            if ($searchCommand->getDefinition()->getClass() === LandingPageSalesChannelDefinition::class && $searchCommand instanceof InsertCommand) {
                $searchPrimaryKey = $searchCommand->getPrimaryKey();
                $searchLandingPageId = $searchPrimaryKey['landing_page_id'] ?? null;

                $currentPrimaryKey = $command->getPrimaryKey();
                $currentLandingPageId = $currentPrimaryKey['id'] ?? null;

                if ($searchLandingPageId === $currentLandingPageId) {
                    $isValid = true;
                }
            }
        }

        return $isValid;
    }
}
