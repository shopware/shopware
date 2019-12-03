<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Error\LoaderError;

interface TemplateFinderInterface
{
    public function registerBundles(KernelInterface $kernel): void;

    public function getTemplateName(string $template): string;

    /**
     * A custom template resolving function is needed to allow multi inheritance of template.
     * This function will check if any other bundle tries to extend the requested template and
     * returns the path to the extending template. Otherwise the original path will be returned.
     *
     * @param string      $template      Path of the requested template, ideally with @Bundle prefix
     * @param bool        $ignoreMissing If set to true no error is throw if the template is missing
     * @param string|null $source        Name of the bundle which triggered the search
     *
     * @throws LoaderError
     */
    public function find(string $template, $ignoreMissing = false, ?string $source = null): string;
}
