<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

class MailHeaderFooterGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var MailHeaderFooterDefinition
     */
    private $mailHeaderFooterDefinition;

    public function __construct(EntityWriterInterface $writer, MailHeaderFooterDefinition $mailHeaderFooterDefinition)
    {
        $this->writer = $writer;
        $this->mailHeaderFooterDefinition = $mailHeaderFooterDefinition;
    }

    public function getDefinition(): string
    {
        return MailHeaderFooterDefinition::class;
    }

    /**
     * @throws \Exception
     */
    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->createMailHeaderFooter(
            $context,
            $numberOfItems
        );
    }

    /**
     * @throws \Exception
     */
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
     * @throws \Exception
     */
    private function prepareHeaderFooterData(DemodataContext $context): array
    {
        $faker = $context->getFaker();
        $mailHeaderFooter = [
            'id' => Uuid::randomHex(),
            'name' => $faker->text(50),
            'description' => $faker->text,
            'isSystemDefault' => false,
            'headerHtml' => $this->generateRandomHTML(
                10,
                ['b', 'i', 'u', 'p', 'h1', 'h2', 'h3', 'h4', 'cite'],
                $context
            ),
            'headerPlain' => $faker->text,
            'footerHtml' => $this->generateRandomHTML(
                10,
                ['b', 'i', 'u', 'p', 'h1', 'h2', 'h3', 'h4', 'cite'],
                $context
            ),
            'footerPlain' => $faker->text,
        ];

        return $mailHeaderFooter;
    }

    private function write(array $payload, DemodataContext $context): void
    {
        $writeContext = WriteContext::createFromContext($context->getContext());

        $this->writer->upsert($this->mailHeaderFooterDefinition, $payload, $writeContext);
    }

    /**
     * @throws \Exception
     *
     * @return string
     */
    private function generateRandomHTML(int $count, array $tags, DemodataContext $context)
    {
        $output = '';
        for ($i = 0; $i < $count; ++$i) {
            $tag = Random::getRandomArrayElement($tags);
            $text = $context->getFaker()->words(random_int(1, 10), true);
            $output .= sprintf('<%1$s>%2$s</%1$s>', $tag, $text);
            $output .= '<br/>';
        }

        return $output;
    }
}
