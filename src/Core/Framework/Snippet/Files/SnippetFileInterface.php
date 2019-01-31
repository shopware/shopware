<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Files;

interface SnippetFileInterface
{
    const BASE_LANGUAGE_FILE = true;

    const PLUGIN_LANGUAGE_EXTENSION_FILE = false;

    /**
     * Returns the displayed name.
     *
     * Example:
     * messages.en_GB
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Returns the path to the json language file.
     *
     * Example:
     * /appPath/subDirectory/messages.en_GB.json
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Returns the associated language ISO.
     *
     * Example:
     * en_GB
     * de_DE
     *
     * @return string
     */
    public function getIso(): string;

    /**
     * Return the snippet author, which will be used when editing a file snippet in a snippet set
     *
     * Example:
     * shopware
     * pluginName
     *
     * @return string
     */
    public function getAuthor(): string;

    /**
     * Returns a boolean which determines if its a base language file
     *
     * @return bool
     */
    public function isBase(): bool;
}
