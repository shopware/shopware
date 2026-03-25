<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter;

use Shopware\Core\Content\ContentSystem\AbstractRenderingSpecificationFactory;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\CategoryContentLayout\CategoryContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Context factory for category-based content layout rendering.
 *
 * Performs path prefix validation and delegates to EntityLayoutContextFactory
 * for creation of the rendering specification.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class CategoryContentLayoutContextFactory extends AbstractRenderingSpecificationFactory
{
    /**
     * @param EntityRepository<CategoryContentLayoutCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly CategoryContentLayoutDefinition $definition,
        private readonly EntityLayoutContextFactory $contextFactory
    ) {
    }

    public function getDecorated(): AbstractRenderingSpecificationFactory
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(string $path, Request $request, SalesChannelContext $context): ?RenderingSpecification
    {
        $path = '/' . ltrim($path, '/');
        $pathPrefix = $this->definition->getContentLayoutPathPrefix();

        if (!str_starts_with($path, $pathPrefix)) {
            return null;
        }

        return $this->contextFactory->create(
            $path,
            $request,
            $context,
            $this->repository, // @phpstan-ignore-line argument.type
            $this->definition
        );
    }
}
