<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Files;

interface SnippetFileInterface
{
    /**
     * Returns the displayed name.
     *
     * Example:
     * messages.en_GB
     */
    public function getName(): string;

    /**
     * Returns the path to the json language file.
     *
     * Example:
     * /appPath/subDirectory/messages.en_GB.json
     */
    public function getPath(): string;

    /**
     * Returns the associated language ISO.
     *
     * Example:
     * en_GB
     * de_DE
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
