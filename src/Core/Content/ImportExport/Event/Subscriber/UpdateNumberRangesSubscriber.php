<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateEntity;
use Shopware\Core\System\NumberRange\NumberRangeEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternDate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UpdateNumberRangesSubscriber implements EventSubscriberInterface
{
    private EntityRepositoryInterface $numberRangeRepository;

    public function __construct(EntityRepositoryInterface $numberRangeRepository)
    {
        $this->numberRangeRepository = $numberRangeRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            ImportExportAfterImportRecordEvent::class => 'onAfterImportRecord',
        ];
    }

    public function onAfterImportRecord(ImportExportAfterImportRecordEvent $event): void
    {
        $entityName = $event->getConfig()->get('sourceEntity');
        $entityWrittenEvents = $event->getResult()->getEvents();

        if (!$entityWrittenEvents) {
            return;
        }

        $entityWrittenEvent = $entityWrittenEvents->filter(function ($event) use ($entityName) {
            return $event instanceof EntityWrittenEvent && $entityName === $event->getEntityName();
        })->first();

        if (!$entityWrittenEvent instanceof EntityWrittenEvent) {
            return;
        }

        $writeResults = $entityWrittenEvent->getWriteResults();

        foreach ($writeResults as $writeResult) {
            $payload = $writeResult->getPayload();

            switch ($entityWrittenEvent->getEntityName()) {
                case ProductDefinition::ENTITY_NAME:
                    $this->updateNumberRange(ProductDefinition::ENTITY_NAME, $payload['productNumber'] ?? null);

                    break;
                case CustomerDefinition::ENTITY_NAME:
                    $this->updateNumberRange(CustomerDefinition::ENTITY_NAME, $payload['customerNumber'] ?? null);
            }
        }
    }

    private function updateNumberRange(string $entityName, ?string $number): void
    {
        if (!$number) {
            return;
        }

        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addAssociation('state');
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('type.technicalName', $entityName),
            new EqualsFilter('global', true),
        ]));

        $numberRangeEntity = $this->numberRangeRepository->search($criteria, $context)->first();

        if (!$numberRangeEntity instanceof NumberRangeEntity) {
            return;
        }

        $state = $numberRangeEntity->getState();
        if (!$state) {
            $state = new NumberRangeStateEntity();
            $state->setId(Uuid::randomHex());
            $state->setLastValue(0);
        }

        // try to parse the iterating number from the given product/customer number
        $numberIteration = $this->parseNumberIteration($numberRangeEntity->getPattern(), $number);

        if (!$numberIteration || $numberIteration <= $state->getLastValue()) {
            return;
        }

        $this->numberRangeRepository->update([
            [
                'id' => $numberRangeEntity->getId(),
                'state' => [
                    'id' => $state->getId(),
                    'lastValue' => $numberIteration,
                ],
            ],
        ], $context);
    }

    private function parseNumberIteration(?string $pattern, string $number): ?int
    {
        if (!$pattern || strpos($pattern, '{n}') === false) {
            return null;
        }

        $patternParts = explode('{n}', $pattern);
        list($patternBefore, $patternAfter) = $patternParts;

        $number = $this->replaceDatePlaceholders(
            $this->replaceDatePlaceholders($number, $patternBefore),
            $patternAfter,
            true
        );

        $numberSearchRegex = sprintf('/^%s$/', str_replace('\{n\}', '([0-9]+)', preg_quote($pattern)));
        preg_match($numberSearchRegex, $number, $numberMatch);

        if (!empty($numberMatch[1]) && is_numeric($numberMatch[1])) {
            return (int) $numberMatch[1];
        }

        return null;
    }

    private function replaceDatePlaceholders(string $number, string $pattern, bool $reverseSearch = false): string
    {
        preg_match_all('/{date(.*?)}/', $pattern, $matches);
        // reverse time formats to placeholders closest to the end of the number string first
        $timeFormats = $reverseSearch ? array_reverse($matches[1]) : $matches[1];

        foreach ($timeFormats as $timeFormat) {
            // if format parameters contain values without leading zeros we can't reliably deduct dates
            if (preg_match('/[jngG]/', $timeFormat) === 1) {
                return $number;
            }

            $replace = !empty($timeFormat) ? sprintf('{date%s}', $timeFormat) : '{date}';
            $timeFormat = !empty($timeFormat) ? trim($timeFormat, '_') : ValueGeneratorPatternDate::STANDARD_FORMAT;

            // reverse character iteration to match date placeholders to the right of {n} placeholder
            // regular character iteration to match date placeholders to the left of the {n} placeholder
            $loopInit = function () use ($number, $reverseSearch): int {
                return $reverseSearch ? \strlen($number) - 1 : 0;
            };
            $loopEnd = function (int $offset) use ($number, $reverseSearch): bool {
                return $reverseSearch ? $offset !== 0 : $offset < \strlen($number);
            };
            $loopEval = function (int &$offset) use ($reverseSearch): void {
                if ($reverseSearch) {
                    --$offset;
                } else {
                    ++$offset;
                }
            };

            for ($offset = $loopInit(); $loopEnd($offset); $loopEval($offset)) {
                $parsedFormats = $this->parseDateFormats(
                    $timeFormat,
                    $number,
                    $offset,
                    $replace
                );
                if ($parsedFormats) {
                    $number = $parsedFormats;

                    break;
                }
            }
        }

        return $number;
    }

    private function parseDateFormats(string $timeFormat, string $number, int $offset, string $replace): ?string
    {
        $parsedDate = date_parse_from_format($timeFormat, substr($number, $offset));

        if (\in_array('Unexpected data found.', $parsedDate['errors'], true) || !empty($parsedDate['warnings'])) {
            return null;
        }

        $endOffset = (int) array_search('Trailing data', $parsedDate['errors'], true) ?: (\strlen($number) - $offset);

        if (!$this->validateDate(substr($number, $offset, $endOffset), $timeFormat)) {
            return null;
        }

        return substr_replace($number, $replace, $offset, $endOffset);
    }

    private function validateDate(string $date, string $format): bool
    {
        $dateFromFormat = \DateTime::createFromFormat($format, $date);

        return $dateFromFormat && $dateFromFormat->format($format) === $date;
    }
}
