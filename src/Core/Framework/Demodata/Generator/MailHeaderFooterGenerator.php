<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class MailHeaderFooterGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly MailHeaderFooterDefinition $mailHeaderFooterDefinition
    ) {
    }

    public function getDefinition(): string
    {
        return MailHeaderFooterDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->createMailHeaderFooter(
            $context,
            $numberOfItems
        );
    }

    private function createMailHeaderFooter(DemodataContext $context, int $numberOfItems): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $mailHeaderFooter = $this->prepareHeaderFooterData($context);

            $payload[] = $mailHeaderFooter;

            if (\count($payload) >= 50) {
                $context->getConsole()->progressAdvance(\count($payload));
                $this->write($payload, $context);
                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->write($payload, $context);
        }

        $context->getConsole()->progressFinish();
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareHeaderFooterData(DemodataContext $context): array
    {
        $faker = $context->getFaker();

        return [
            'id' => Uuid::randomHex(),
            'name' => $faker->text(50),
            'description' => $faker->text(),
            'isSystemDefault' => false,
            'headerHtml' => $this->generateRandomHTML(
                10,
                ['b', 'i', 'u', 'p', 'h1', 'h2', 'h3', 'h4', 'cite'],
                $context
            ),
            'headerPlain' => $faker->text(),
            'footerHtml' => $this->generateRandomHTML(
                10,
                ['b', 'i', 'u', 'p', 'h1', 'h2', 'h3', 'h4', 'cite'],
                $context
            ),
            'footerPlain' => $faker->text(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $payload
     */
    private function write(array $payload, DemodataContext $context): void
    {
        $writeContext = WriteContext::createFromContext($context->getContext());

        $this->writer->upsert($this->mailHeaderFooterDefinition, $payload, $writeContext);
    }

    /**
     * @param list<string> $tags
     */
    private function generateRandomHTML(int $count, array $tags, DemodataContext $context): string
    {
        $output = '';
        for ($i = 0; $i < $count; ++$i) {
            $tag = Random::getRandomArrayElement($tags);
            $text = $context->getFaker()->words(random_int(1, 10), true);
            if (\is_array($text)) {
                $text = implode(' ', $text);
            }
            $output .= sprintf('<%1$s>%2$s</%1$s>', $tag, $text);
            $output .= '<br/>';
        }

        return $output;
    }
}
