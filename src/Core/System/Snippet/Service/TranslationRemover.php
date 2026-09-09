<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Event\TranslationRemovedEvent;
use Shopware\Core\System\Snippet\SnippetException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
readonly class TranslationRemover
{
    public function __construct(
        private FilesystemOperator $translationWriter,
        private AbstractTranslationLoader $translationLoader,
        private TranslationMetadataStore $metadataStore,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Uninstalls a locale by deleting its downloaded translation files and dropping its metadata entry.
     * The language, locale and snippet set records are left untouched.
     */
    public function remove(string $locale): void
    {
        $localePath = $this->translationLoader->getLocalePath($locale);

        if ($localePath === '') {
            throw SnippetException::localeDoesNotExist($locale);
        }

        if ($this->translationWriter->directoryExists($localePath)) {
            $this->translationWriter->deleteDirectory($localePath);
        }

        $this->metadataStore->remove($locale);

        $this->eventDispatcher->dispatch(new TranslationRemovedEvent($locale));
    }
}
