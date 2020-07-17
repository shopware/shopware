<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

/**
 * @deprecated tag:v6.4 use the automatic snippet loading by providing your snippets in the right dir with the right name
 */
interface SnippetFileInterface
{
    /**
     * Returns the displayed name.
     *
     * Example:
     * storefront.en-GB
     */
    public function getName(): string;

    /**
     * Returns the path to the json language file.
     *
     * Example:
     * /appPath/subDirectory/storefront.en-GB.json
     */
    public function getPath(): string;

    /**
     * Returns the associated language ISO.
     *
     * Example:
     * en-GB
     * de-DE
     */
    public function getIso(): string;

    /**
     * Return the snippet author, which will be used when editing a file snippet in a snippet set
     *
     * Example:
     * shopware
     * pluginName
     */
    public function getAuthor(): string;

    /**
     * Returns a boolean which determines if its a base language file
     */
    public function isBase(): bool;
}
