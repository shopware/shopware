<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ExtendableTrait;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    replacement: AbstractDocumentDataProvider::class,
    description: 'Provide additional template variables via provideRenderingData(). Public properties of the returned render data are exposed to the template.',
)]
class DocumentTemplateRendererParameterEvent extends Event
{
    use ExtendableTrait;

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(private readonly array $parameters)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
