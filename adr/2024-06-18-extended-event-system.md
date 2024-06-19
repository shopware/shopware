---
title: Transition to an Event-Based Extension System
date: 2024-06-18
area: core
tags: [core, plugin, event]
---

## Context

In our current architecture, we rely heavily on PHP decoration, Adapter, and Factory patterns to allow for extensions and customizations by third-party developers. While these patterns are effective, they present significant challenges:

1. **Backward and Forward Compatibility:**
    - Maintaining backward and forward compatibility with these patterns is complex and labor-intensive. Each change or update can potentially break existing extensions or require extensive rework to ensure compatibility.

2. **Process Extension Limitations:**
    - These patterns do not inherently allow for the extension of subprocesses unless these subprocesses are extracted into separate classes and interfaces. This extraction often results in a proliferation of interfaces, abstract classes, and their implementations.

3. **Proliferation of Code:**
    - The need to extract subprocesses into separate entities leads to an overwhelming number of interfaces and abstract classes. This proliferation makes the codebase more difficult to understand and maintain, and increases the cognitive load on developers.

## Decision

To address these challenges, we have decided to transition to an event-based extension system. This new approach will replace the existing decoration, Adapter, and Factory patterns as the primary method for extending and customizing our system.

## Rationale

1. **Simplification of Compatibility:**
    - An event-based system inherently simplifies backward and forward compatibility. Events can be introduced, deprecated, or modified with minimal impact on existing extensions, as long as the core event structure remains consistent.

2. **Modular Extension Points:**
    - By leveraging events, we can provide more granular and modular extension points. Developers can hook into specific points of the application flow without needing to manipulate or extend multiple interfaces and classes.

3. **Reduction in Code Proliferation:**
    - The shift to an event-based system will significantly reduce the need for a large number of interfaces and abstract classes. This will streamline the codebase, making it easier to manage and reducing the cognitive load on developers.

4. **Unified Extension Framework:**
    - An event-based system provides a more unified and consistent framework for third-party developers. They can use a standardized method to extend and customize the application, leading to better consistency and reliability in extensions.

## Consequences

1. **Initial Refactoring Effort:**
    - Transitioning to an event-based system will require an initial effort to refactor existing code and extensions. This will involve identifying current extension points and replacing them with event triggers.

2. **Learning Curve:**
    - Developers accustomed to the current patterns will need to adapt to the new event-based approach. Training and documentation will be necessary to facilitate this transition.

## Implementation

1. **Identify Key Extension Points:**
    - Conduct an audit of the current system to identify key extension points that will be replaced with events.

2. **Define Event Structure:**
    - Develop a standard structure for events, including naming conventions, payload formats, and handling mechanisms.

3. **Refactor Existing Extensions:**
    - Gradually refactor existing extensions to use the new event-based system, ensuring backward compatibility where necessary.

4. **Documentation and Training:**
    - Create comprehensive documentation and training materials to help developers transition to the new system.

## Conclusion

The transition to an event-based extension system represents a strategic shift aimed at simplifying our extension framework, improving maintainability, and providing a more consistent and flexible platform for third-party developers. While this change requires an initial investment in refactoring and training, the long-term benefits of reduced complexity, improved compatibility, and a unified extension approach make it a worthwhile endeavor.

## Example

The following example demonstrates how an event-based extension can be implemented in the context of resolving product listings:

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Extension;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends Extension<EntitySearchResult<ProductCollection>>
 */
#[Package('inventory')]
final class ResolveListingExtension extends Extension
{
    public const NAME = 'listing-loader.resolve';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The criteria which should be used to load the products. Is also containing the selected customer filter
         */
        public readonly Criteria $criteria,
        /**
         * @public
         *
         * @description Allows you to access to the current customer/sales-channel context
         */
        public readonly SalesChannelContext $context
    ) {
    }

    public static function name(): string
    {
        return self::NAME;
    }
}
```

In this example, the `ResolveListingExtension` class represents an event-based extension point for resolving product listings. Developers can subscribe to this event and provide custom logic for loading product data based on specific criteria and context. This approach allows for more modular and flexible extensions compared to traditional patterns like decoration or Adapter.
```php
<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use GuzzleHttp\ClientInterface;
use Shopware\Core\Content\Product\Extension\ResolveListingExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class ResolveListingExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'listing-loader.resolve-listing-ids.pre' => 'replace',
        ];
    }

    /**
     * @param EntityRepository<ProductCollection> $repository
     */
    public function __construct(
        // you can inject your own services
        private ClientInterface $client,
        private EntityRepository $repository
    ) {
    }

    public function replace(ResolveListingExtension $event): void
    {
        $criteria = $event->criteria;

        // building a json aware array for the API call
        $context = [
            'salesChannelId' => $event->context->getSalesChannelId(),
            'currencyId' => $event->context->getCurrency(),
            'languageId' => $event->context->getLanguageId(),
        ];

        // do an api call against your own server or another storage, or whatever you want
        $ids = $this->client->request('GET', 'https://your-api.com/listing-ids', [
            'query' => [
                'criteria' => json_encode($criteria),
                'context' => json_encode($context),
            ],
        ]);

        $data = json_decode($ids->getBody()->getContents(), true);

        $criteria = new Criteria($data['ids']);

        $event->result = $this->repository->search($criteria, $event->context->getContext());

        // stop the event propagation, so the core function will not be executed
        $event->stopPropagation();
    }
}
```
