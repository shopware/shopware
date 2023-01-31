<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Log\Package;
use Twig\Error\LoaderError;

#[Package('core')]
interface TemplateFinderInterface
{
    public function getTemplateName(string $template): string;

    /**
     * A custom template resolving function is needed to allow multi inheritance of template.
     * This function will check if any other bundle tries to extend the requested template and
     * returns the path to the extending template. Otherwise the original path will be returned.
     *
     * @param string      $template      Path of the requested template, ideally with @Bundle prefix
     * @param bool        $ignoreMissing If set to true no error is throw if the template is missing
     * @param string|null $source        Source template path that triggered the search includes @Bundle prefix.
     *                                   The full source template path is necessary as extending a different file in the same bundle needs to use the normal inheritance hierarchy.
     *
     * @throws LoaderError
     */
    public function find(string $template, $ignoreMissing = false, ?string $source = null): string;
}
