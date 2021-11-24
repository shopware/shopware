<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Twig\Environment;

class TwigEnvironment extends Environment
{
    public function render($name, array $context = []): string
    {
        $template = $this->load($name);

        try {
            FieldVisibility::$isInTwigRenderingContext = true;
            $result = $template->render($context);
        } finally {
            FieldVisibility::$isInTwigRenderingContext = false;
        }

        return $result;
    }

    public function display($name, array $context = []): void
    {
        $template = $this->load($name);

        try {
            FieldVisibility::$isInTwigRenderingContext = true;
            $template->display($context);
        } finally {
            FieldVisibility::$isInTwigRenderingContext = false;
        }
    }
}
