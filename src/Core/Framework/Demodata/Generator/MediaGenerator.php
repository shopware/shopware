<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Ramsey\Uuid\Uuid;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Symfony\Component\Finder\Finder;

class MediaGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var FileSaver
     */
    private $mediaUpdater;

    /**
     * @var FileNameProvider
     */
    private $fileNameProvider;

    public function __construct(EntityWriterInterface $writer, FileSaver $mediaUpdater, FileNameProvider $fileNameProvider)
    {
        $this->writer = $writer;
        $this->mediaUpdater = $mediaUpdater;
        $this->fileNameProvider = $fileNameProvider;
    }

    public function getDefinition(): string
    {
        return MediaDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $writeContext = WriteContext::createFromContext($context->getContext());

        for ($i = 0; $i < $numberOfItems; ++$i) {
            $file = $this->getRandomFile();

            $mediaId = Uuid::uuid4()->getHex();
            $this->writer->insert(
                MediaDefinition::class,
                [['id' => $mediaId, 'name' => "File #{$i}: {$file}"]],
                $writeContext
            );

            $this->mediaUpdater->persistFileToMedia(
                new MediaFile(
                    $file,
                    mime_content_type($file),
                    pathinfo($file, PATHINFO_EXTENSION),
                    filesize($file)
                ),
                $this->fileNameProvider->provide(
                    pathinfo($file, PATHINFO_FILENAME),
                    pathinfo($file, PATHINFO_EXTENSION),
                    $mediaId,
                    $context->getContext()
                ),
                $mediaId,
                $context->getContext()
            );

            $context->getConsole()->progressAdvance(1);
            $context->add(MediaDefinition::class, $mediaId);
        }

        $context->getConsole()->progressFinish();
    }

    private function getRandomFile(): string
    {
        $files = array_keys(iterator_to_array(
            (new Finder())
                ->files()
                ->in(__DIR__ . '/../../Resources/demo-media')
                ->getIterator()
        ));

        return $files[array_rand($files)];
    }
}
