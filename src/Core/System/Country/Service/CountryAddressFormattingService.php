<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Service;

use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Country\Struct\CountryAddress;

class CountryAddressFormattingService
{
    private StringTemplateRenderer $templateRenderer;

    /**
     * @internal
     */
    public function __construct(StringTemplateRenderer $templateRenderer)
    {
        $this->templateRenderer = $templateRenderer;
    }

    public function render(CountryAddress $addressData, ?string $template, Context $context): string
    {
        if ($template === null) {
            return '';
        }

        $this->templateRenderer->enableTestMode();
        $content = $this->optimisedTemplateRendered($addressData, $template, $context);
        $this->templateRenderer->disableTestMode();

        return $content;
    }

    private function optimisedTemplateRendered(CountryAddress $addressData, string $template, Context $context): string
    {
        $originalTemplateLines = explode(\PHP_EOL, $template);
        $renderedTemplate = $this->templateRenderer->render($template, $addressData->toArray(), $context);
        $renderedTemplateLines = explode(\PHP_EOL, $renderedTemplate);
        $contents = [];
        foreach ($originalTemplateLines as $index => $originalTemplateLine) {
            if (trim($renderedTemplateLines[$index]) === '' && trim($originalTemplateLine) !== '') {
                continue;
            }

            $contents[] = trim($renderedTemplateLines[$index]);
        }

        return implode(\PHP_EOL, $contents);
    }
}
