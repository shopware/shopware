# Shopware Content System Element Hydration and Response Building

## Overview

This document describes how Content System entities are loaded from the database, transformed, and built into API responses. While the [Entity Architecture](./shopware-cms-v2-entity-architecture.md) defines database structure, this document focuses on runtime data loading, transformation, and response composition.

## Three-Tier Element Classification

Elements in the Content System are classified into three categories based on their data source and loading pattern:

### 1. Container/Layout Elements (Pure Structure)

These elements have NO associated entities and fetch NO data during primary resolution. They exist solely to organize other elements.

**Examples**: `grid`, `row`, `column`, `section`, `block`, `tabs`, `accordion`, `carousel`

```json
{
  "type": "grid",
  "version": "1.0.0",
  "data": {
    "columns": 12,
    "gap": "medium"
  },
  "elements": [...] // Child elements
}
```

**Characteristics:**
- No extension table needed
- No database queries beyond initial element load
- Config contains only layout parameters
- Act as containers for child elements
- Fully cacheable

### 2. Static Content Elements (Inline Data)

These elements contain their data directly in the `config` field. No entity resolution needed.

**Examples**: `heading`, `text`, `button`, `html`, `icon`, `divider`, `spacer`

```json
{
  "type": "heading",
  "version": "1.0.0", 
  "data": {
    "text": "Welcome to our store",  // Static text from config
    "level": 2
  }
}
```

**Characteristics:**
- All data stored in `content_element.config` JSON field
- No foreign key relationships
- No additional queries during hydration
- Data immediately available from element entity
- Fully cacheable

### 3. Entity-Associated Elements (Database Loaded)

These elements load data through DAL associations via extension tables.

**Examples**: `product-box`, `category-navigation`, `media-image`, `manufacturer-logo`

```json
{
  "type": "product-box",
  "version": "1.0.0",
  "data": {
    "displayMode": "full",
    "product": { /* Hydrated product entity */ }
  }
}
```

**Characteristics:**
- Have corresponding extension table (e.g., `cms_v2_element_product`)
- Load data via DAL associations
- Batch-loadable for performance
- Context-aware caching

### 4. Service-Loaded Elements (Deferred/Dynamic Loading)

These load data from services AFTER the primary entity resolution phase.

**Examples**: `cart-full`, `cart-mini`, `wishlist`, `user-menu`, `search-suggestions`, `recently-viewed`

```json
{
  "type": "cart-full",
  "version": "1.0.0",
  "data": {
    "displayMode": "full",
    "cart": { /* Loaded via CartService */ }
  }
}
```

**Characteristics:**
- **Session-based**: Tied to session token, not database entity
- **Context-dependent**: User-specific, sales channel specific
- **Real-time**: Must reflect current state
- **Complex calculation**: Requires business logic beyond data fetch
- Cannot be cached or require special cache invalidation

## Element Type Classification Table

| Element Type | Data Source | Extension Table | Loading Phase | Cache Strategy |
|--------------|-------------|-----------------|---------------|----------------|
| **Container Elements** | | | | |
| grid | None | ❌ | N/A | Full cache |
| section | None | ❌ | N/A | Full cache |
| column | None | ❌ | N/A | Full cache |
| tabs | None | ❌ | N/A | Full cache |
| accordion | None | ❌ | N/A | Full cache |
| **Static Elements** | | | | |
| heading | config field | ❌ | Immediate | Full cache |
| text | config field | ❌ | Immediate | Full cache |
| button | config field | ❌ | Immediate | Full cache |
| html | config field | ❌ | Immediate | Full cache |
| icon | config field | ❌ | Immediate | Full cache |
| **Entity Elements** | | | | |
| product-box | Product entity | ✅ content_element_product | Phase 1 | Context cache |
| product-listing | Product entity | ✅ content_element_product | Phase 1 | Context cache |
| category-nav | Category entity | ✅ content_element_category | Phase 1 | Context cache |
| media-image | Media entity | ✅ content_element_media | Phase 1 | Full cache |
| manufacturer-logo | Manufacturer entity | ✅ content_element_manufacturer | Phase 1 | Full cache |
| order-details | Order entity | ✅ content_element_order | Phase 1 | User cache |
| customer-profile | Customer entity | ✅ content_element_customer | Phase 1 | User cache |
| **Service Elements** | | | | |
| cart-full | CartService | ❌ | Phase 3 | No cache |
| cart-mini | CartService | ❌ | Phase 3 | No cache |
| cart-totals | CartService | ❌ | Phase 3 | No cache |
| wishlist | WishlistService | ❌ | Phase 3 | User cache |
| user-menu | SalesChannelContext | ❌ | Phase 3 | Session cache |
| search-bar | None (UI only) | ❌ | N/A | Full cache |
| currency-switcher | CurrencyRoute | ❌ | Phase 3 | Context cache |
| language-switcher | LanguageRoute | ❌ | Phase 3 | Context cache |

## Hydration Process

The hydration process transforms database entities into API-ready response structures through three distinct phases, with optional type discovery for optimized association loading:

### Phase 0: Type Discovery (Optional Optimization)

When using the content element type entity, perform type discovery for optimized association loading:

```php
// Lightweight query to discover element types
$types = $connection->fetchFirstColumn(
    'SELECT DISTINCT type FROM content_element WHERE template_id = :id',
    ['id' => $templateId]
);

// Load type entities for metadata
$typeEntities = $typeRepository->findByKeys($types);

// Fire event for custom association requirements
$event = new ContentElementTypesDiscoveredEvent($types, $typeEntities, $templateId);
$eventDispatcher->dispatch($event);
```

### Phase 1: Entity Resolution (DAL Associations)

Load elements with their entity associations from the database.

```php
// Build criteria with default associations
$criteria = new Criteria();
$criteria->addFilter(new EqualsFilter('templateId', $templateId));
$criteria->addAssociation('children'); // Recursive loading
$criteria->addSorting(new FieldSorting('position'));

// Default associations that cover most use cases
$defaultAssociations = [
    'productElement.product.prices.currency',
    'productElement.product.manufacturer',
    'productElement.product.media',
    'categoryElement.category.children',
    'mediaElement.media',
    'manufacturerElement.manufacturer',
    'orderElement.order.lineItems',
    'customerElement.customer.addresses',
];

foreach ($defaultAssociations as $association) {
    $criteria->addAssociation($association);
}

// Fire event for plugins to add custom associations
$event = new ContentElementAssociationEvent($criteria, $templateId, $context);
$eventDispatcher->dispatch($event);

// Execute optimized query
$elements = $elementRepository->search($criteria, $context);
```

### Phase 2: Static Data Processing

Format static elements that have their data in the config field.

```php
private function processStaticElements(ContentElementCollection $elements): void
{
    foreach ($elements as $element) {
        if ($this->isStaticElement($element->getType())) {
            // Data already in config, just ensure proper structure
            $config = $element->getConfig() ?? [];
            
            // Extract style and attributes for API response structure
            $element->setProcessedData([
                'data' => $this->extractData($config),
                'style' => $config['style'] ?? null,
                'attributes' => $config['attributes'] ?? null
            ]);
        }
    }
}
```

### Phase 3: Service Loading (Deferred)

Load data from services for elements that require runtime information.

```php
private function loadServiceData(ContentElementCollection $elements, SalesChannelContext $context): void
{
    foreach ($elements as $element) {
        switch ($element->getType()) {
            case 'cart-full':
            case 'cart-mini':
            case 'cart-summary':
                $this->loadCartData($element, $context);
                break;
                
            case 'wishlist':
                $this->loadWishlistData($element, $context);
                break;
                
            case 'user-menu':
                $this->loadUserData($element, $context);
                break;
                
            case 'currency-switcher':
                $this->loadCurrencies($element, $context);
                break;
        }
    }
}

private function loadCartData(ContentElementEntity $element, SalesChannelContext $context): void
{
    $cart = $this->cartService->getCart($context->getToken(), $context);
    
    // Extract product IDs for potential batch loading
    $productIds = [];
    foreach ($cart->getLineItems()->filterByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $item) {
        $productIds[] = $item->getReferencedId();
    }
    
    // Products can be batch-loaded if needed
    if (!empty($productIds)) {
        $products = $this->productLoader->load($productIds, $context);
        // Enrich cart items with full product data
        $this->enrichCartItems($cart, $products);
    }
    
    $element->addData(['cart' => $this->serializeCart($cart)]);
}
```

### Lazy Loading Support

Elements marked for lazy loading are skipped during initial hydration:

```php
if ($element->isLazyLoad()) {
    // Skip hydration, just mark for lazy loading
    $element->setData(['lazyLoad' => true]);
    continue;
}
```

### Caching Strategy

Different element types require different caching strategies:

- **Full Cache**: Container and static elements (invalidate on CMS change)
- **Context Cache**: Entity elements (invalidate on entity or context change)
- **User Cache**: User-specific elements (invalidate on user change)
- **Session Cache**: Session-specific elements (invalidate on session change)
- **No Cache**: Real-time elements like cart (always fresh)

## Query Optimization

### Batch Loading Strategy

Collect all required entity IDs before loading to minimize queries:

```php
class ContentElementLoader
{
    public function load(ContentElementCollection $elements, SalesChannelContext $context): void
    {
        // Step 1: Collect all entity IDs by type
        $productIds = [];
        $categoryIds = [];
        $mediaIds = [];
        
        foreach ($elements->getFlat() as $element) {
            switch ($element->getType()) {
                case 'product-box':
                case 'product-listing':
                    if ($element->getProductElement()) {
                        $productIds[] = $element->getProductElement()->getProductId();
                    }
                    break;
                    
                case 'category-navigation':
                    if ($element->getCategoryElement()) {
                        $categoryIds[] = $element->getCategoryElement()->getCategoryId();
                    }
                    break;
                    
                case 'media-image':
                    if ($element->getMediaElement()) {
                        $mediaIds[] = $element->getMediaElement()->getMediaId();
                    }
                    break;
            }
        }
        
        // Step 2: Batch load all entities
        $products = $this->loadProducts(array_unique($productIds), $context);
        $categories = $this->loadCategories(array_unique($categoryIds), $context);
        $media = $this->loadMedia(array_unique($mediaIds), $context);
        
        // Step 3: Assign loaded entities back to elements
        foreach ($elements->getFlat() as $element) {
            if ($element->getProductElement() && isset($products[$element->getProductElement()->getProductId()])) {
                $element->getProductElement()->setProduct($products[$element->getProductElement()->getProductId()]);
            }
            // ... similar for categories and media
        }
    }
}
```

### Cart Product Pre-Loading

For cart elements, extract and pre-load all product IDs:

```php
class CartElementProcessor
{
    public function preloadCartProducts(Cart $cart, SalesChannelContext $context): ProductCollection
    {
        // Extract all product IDs from cart
        $productIds = [];
        foreach ($cart->getLineItems()->getFlat() as $item) {
            if ($item->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                $productIds[] = $item->getReferencedId();
            }
        }
        
        if (empty($productIds)) {
            return new ProductCollection();
        }
        
        // Single optimized query with all needed associations
        $criteria = new Criteria(array_unique($productIds));
        $criteria->addAssociation('media');
        $criteria->addAssociation('prices');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('categories');
        
        return $this->productRepository->search($criteria, $context)->getEntities();
    }
}
```

## Hydration Service Implementation

```php
namespace Shopware\Core\Content\Service;

use Shopware\Core\Content\Entity\ContentElementCollection;
use Shopware\Core\Content\Entity\ContentElementEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ContentHydrationService
{
    public function __construct(
        private readonly EntityRepository $elementRepository,
        private readonly ProductLoader $productLoader,
        private readonly CartService $cartService,
        private readonly WishlistService $wishlistService
    ) {}

    /**
     * Three-phase hydration process
     */
    public function hydrate(string $templateId, SalesChannelContext $context): ContentElementCollection
    {
        // Phase 1: Load elements with entity associations
        $elements = $this->loadElements($templateId, $context->getContext());
        
        // Phase 2: Process static elements
        $this->processStaticElements($elements);
        
        // Phase 3: Load service data
        $this->loadServiceData($elements, $context);
        
        return $elements;
    }
    
    private function loadElements(string $templateId, Context $context): ContentElementCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('templateId', $templateId));
        
        // Add all potential associations
        $criteria->addAssociation('children');
        $criteria->addAssociation('productElement.product');
        $criteria->addAssociation('categoryElement.category');
        $criteria->addAssociation('mediaElement.media');
        $criteria->addAssociation('manufacturerElement.manufacturer');
        $criteria->addAssociation('orderElement.order');
        $criteria->addAssociation('customerElement.customer');
        
        $criteria->addSorting(new FieldSorting('position'));
        
        return $this->elementRepository->search($criteria, $context)->getEntities();
    }
    
    private function processStaticElements(ContentElementCollection $elements): void
    {
        foreach ($elements as $element) {
            if ($this->isStaticElement($element->getType())) {
                // Data already in config, just ensure proper structure
                $config = $element->getConfig() ?? [];
                
                // Extract data, style, and attributes for API response
                $element->setProcessedData($this->extractStaticData($config));
            }
        }
    }
    
    private function loadServiceData(ContentElementCollection $elements, SalesChannelContext $context): void
    {
        // Collect all service-loaded elements
        $cartElements = [];
        $wishlistElements = [];
        
        foreach ($elements->getFlat() as $element) {
            if ($this->isCartElement($element->getType())) {
                $cartElements[] = $element;
            } elseif ($element->getType() === 'wishlist') {
                $wishlistElements[] = $element;
            }
        }
        
        // Load cart once for all cart elements
        if (!empty($cartElements)) {
            $cart = $this->cartService->getCart($context->getToken(), $context);
            $cartData = $this->serializeCart($cart);
            
            foreach ($cartElements as $element) {
                $element->addData(['cart' => $this->filterCartData($cartData, $element->getType())]);
            }
        }
        
        // Load wishlist once for all wishlist elements
        if (!empty($wishlistElements) && $context->getCustomer()) {
            $wishlist = $this->wishlistService->load($context->getCustomer()->getId(), $context);
            
            foreach ($wishlistElements as $element) {
                $element->addData(['wishlist' => $wishlist]);
            }
        }
    }
    
    private function isStaticElement(string $type): bool
    {
        return in_array($type, [
            'heading', 'text', 'button', 'html', 'icon', 'divider', 'spacer'
        ], true);
    }
    
    private function isCartElement(string $type): bool
    {
        return in_array($type, [
            'cart-full', 'cart-mini', 'cart-summary', 'cart-totals'
        ], true);
    }
    
    private function filterCartData(array $cartData, string $elementType): array
    {
        return match($elementType) {
            'cart-mini' => [
                'itemCount' => $cartData['itemCount'],
                'total' => $cartData['total']
            ],
            'cart-totals' => [
                'subtotal' => $cartData['subtotal'],
                'shipping' => $cartData['shipping'],
                'tax' => $cartData['tax'],
                'total' => $cartData['total']
            ],
            default => $cartData
        };
    }
}
```

## Event-Based Association Extension

The system provides events for extending association loading without modifying core code:

```php
/**
 * Event fired before loading elements, allowing custom associations
 */
class ContentElementAssociationEvent extends Event
{
    public function __construct(
        private Criteria $criteria,
        private string $templateId,
        private SalesChannelContext $context
    ) {}
    
    public function getCriteria(): Criteria {
        return $this->criteria;
    }
    
    public function addAssociation(string $association): void {
        $this->criteria->addAssociation($association);
    }
    
    public function getTemplateId(): string {
        return $this->templateId;
    }
}

/**
 * Event fired after type discovery, includes type metadata
 */
class ContentElementTypesDiscoveredEvent extends Event
{
    public function __construct(
        private array $types,
        private ContentElementTypeCollection $typeEntities,
        private string $templateId
    ) {}
    
    public function getTypes(): array {
        return $this->types;
    }
    
    public function getTypeEntities(): ContentElementTypeCollection {
        return $this->typeEntities;
    }
}
```

### Plugin Extension Example

```php
class CustomElementSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array {
        return [
            ContentElementAssociationEvent::class => 'onAssociation',
            ContentElementTypesDiscoveredEvent::class => 'onTypesDiscovered',
        ];
    }
    
    public function onAssociation(ContentElementAssociationEvent $event): void {
        // Add associations for custom element types
        $event->addAssociation('customElement.customEntity.relation');
    }
    
    public function onTypesDiscovered(ContentElementTypesDiscoveredEvent $event): void {
        // React to discovered types
        if (in_array('custom-widget', $event->getTypes(), true)) {
            // Perform custom logic based on type presence
        }
    }
}
```

## Response Builder Implementation

```php
namespace Shopware\Core\Content\Service;

/**
 * Runtime service that builds the Content System API response.
 * This is NOT a database entity - it composes the response at runtime
 * using loaded templates and the current request context.
 */
class ContentResponseBuilder
{
    public function __construct(
        private readonly SeoGenerator $seoGenerator,
        private readonly ContentHydrationService $hydrationService
    ) {}
    
    /**
     * Builds the complete API response structure at runtime.
     * No data is persisted - this is pure response composition.
     */
    public function build(
        ContentTemplateEntity $storeTemplate,
        ContentTemplateEntity $pageTemplate,
        SalesChannelContext $context
    ): array {
        // Hydrate all elements
        $storeElements = $this->hydrationService->hydrate($storeTemplate->getId(), $context);
        $pageElements = $this->hydrationService->hydrate($pageTemplate->getId(), $context);
        
        return [
            'storeTemplate' => $this->buildTemplate($storeTemplate, $storeElements, $context),
            'pageTemplate' => $this->buildTemplate($pageTemplate, $pageElements, $context),
            'context' => [
                'salesChannelId' => $context->getSalesChannelId(),
                'languageId' => $context->getLanguageId(),
                'currencyId' => $context->getCurrencyId(),
                'customerId' => $context->getCustomer()?->getId(),
                'customerGroupId' => $context->getCurrentCustomerGroup()->getId(),
            ],
            'apiVersion' => '6.6.0.0',
            'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
        ];
    }
    
    private function buildTemplate(
        ContentTemplateEntity $template,
        ContentElementCollection $elements,
        SalesChannelContext $context
    ): array {
        return [
            'id' => $template->getId(),
            'type' => $template->getType(),
            'version' => $template->getVersion(),
            'name' => $template->getName(),
            'data' => $template->getData(),
            'elements' => $this->buildElements($elements),
            'seo' => $this->generateSeo($template, $context),
        ];
    }
    
    private function buildElements(?ContentElementCollection $elements): array
    {
        if (!$elements) {
            return [];
        }
        
        $result = [];
        foreach ($elements as $element) {
            $elementData = [
                'id' => $element->getId(),
                'type' => $element->getType(),
                'version' => $element->getVersion(),
            ];
            
            // Build element data based on type
            $elementData['data'] = $this->buildElementData($element);
            
            // Extract style and attributes from config for API response
            $config = $element->getConfig() ?? [];
            if (!empty($config['style'])) {
                $elementData['style'] = $config['style'];
            }
            if (!empty($config['attributes'])) {
                $elementData['attributes'] = $config['attributes'];
            }
            
            // Add slots if present
            if ($element->getChildren()) {
                $slots = $this->groupElementsBySlot($element->getChildren());
                if (!empty($slots)) {
                    $elementData['slots'] = $slots;
                }
                
                // Add direct children (no slot name)
                $directChildren = $element->getChildren()->filter(
                    fn($child) => !$child->getSlotName()
                );
                if ($directChildren->count() > 0) {
                    $elementData['elements'] = $this->buildElements($directChildren);
                }
            }
            
            $result[] = $elementData;
        }
        
        return $result;
    }
    
    private function buildElementData(ContentElementEntity $element): array
    {
        // Start with config data
        $data = $element->getConfig() ?? [];
        
        // Remove style and attributes from data (they're separate in API)
        unset($data['style'], $data['attributes']);
        
        // Add hydrated entity data based on element type
        if ($productElement = $element->getProductElement()) {
            $data['product'] = $this->serializeProduct($productElement->getProduct());
        }
        
        if ($categoryElement = $element->getCategoryElement()) {
            $data['category'] = $this->serializeCategory($categoryElement->getCategory());
        }
        
        if ($mediaElement = $element->getMediaElement()) {
            $data['media'] = $this->serializeMedia($mediaElement->getMedia());
        }
        
        if ($orderElement = $element->getOrderElement()) {
            $data['order'] = $this->serializeOrder($orderElement->getOrder());
        }
        
        if ($customerElement = $element->getCustomerElement()) {
            $data['customer'] = $this->serializeCustomer($customerElement->getCustomer());
        }
        
        // Add any service-loaded data
        if ($element->hasServiceData()) {
            $data = array_merge($data, $element->getServiceData());
        }
        
        return $data;
    }
    
    private function groupElementsBySlot(ContentElementCollection $elements): array
    {
        $slots = [];
        
        foreach ($elements as $element) {
            if ($slotName = $element->getSlotName()) {
                if (!isset($slots[$slotName])) {
                    $slots[$slotName] = [];
                }
                $slots[$slotName][] = $this->buildElement($element);
            }
        }
        
        return $slots;
    }
    
    /**
     * Generates SEO metadata at runtime based on template patterns and entity data.
     */
    private function generateSeo(ContentTemplateEntity $template, SalesChannelContext $context): array
    {
        $seoPatterns = $template->getData()['seoPatterns'] ?? [];
        $primaryEntity = $this->getPrimaryEntity($template, $context);
        
        return $this->seoGenerator->generate($seoPatterns, $primaryEntity, $context);
    }
}
```

## SEO Generation

SEO metadata is generated at runtime based on the current context, loaded entities, and configured patterns:

```php
/**
 * SEO Generator Service - generates metadata from patterns and entities.
 */
class SeoGenerator
{
    public function generate(array $patterns, ?EntityInterface $entity, SalesChannelContext $context): array
    {
        $seo = [];
        
        // Use entity's own SEO fields as primary source
        if ($entity && method_exists($entity, 'getMetaTitle')) {
            $seo['metaTitle'] = $entity->getMetaTitle();
            $seo['metaDescription'] = $entity->getMetaDescription();
        }
        
        // Apply patterns as fallback
        foreach ($patterns as $key => $pattern) {
            if (empty($seo[$key])) {
                $seo[$key] = $this->resolvePattern($pattern, $entity, $context);
            }
        }
        
        // Add context data
        $seo['languageId'] = $context->getLanguageId();
        $seo['canonicalUrl'] = $this->generateCanonicalUrl($entity, $context);
        
        return $seo;
    }
    
    private function resolvePattern(string $pattern, ?EntityInterface $entity, SalesChannelContext $context): string
    {
        // Replace placeholders with actual values
        $replacements = [
            '{shop.name}' => $context->getSalesChannel()->getName(),
            '{product.name}' => $entity?->getName() ?? '',
            '{product.manufacturer.name}' => $entity?->getManufacturer()?->getName() ?? '',
            '{category.name}' => $entity?->getName() ?? '',
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $pattern);
    }
    
    private function generateCanonicalUrl(?EntityInterface $entity, SalesChannelContext $context): string
    {
        // Generate based on entity type and sales channel router
        if ($entity && method_exists($entity, 'getSeoUrls')) {
            $seoUrl = $entity->getSeoUrls()->filter(
                fn ($url) => $url->getSalesChannelId() === $context->getSalesChannelId()
            )->first();
            
            if ($seoUrl) {
                return '/' . $seoUrl->getSeoPathInfo();
            }
        }
        
        return '';
    }
}
```

## Performance Optimization Strategies

### 1. Query Optimization
- Batch load all entities of the same type in single queries
- Use precise associations to avoid over-fetching
- Leverage database indexes on frequently queried fields

### 2. Caching Strategy
- Cache fully rendered elements when possible
- Use different cache pools for different element types
- Implement smart cache invalidation based on data dependencies

### 3. Memory Management
- Load entities once and reference them multiple times
- Clear processed data after response building
- Use generators for large collections

### 4. Service Loading Optimization
- Load service data once for all elements that need it
- Pre-extract entity IDs for batch loading
- Use asynchronous loading where appropriate

## See Also

- [Content System Entity Architecture](./shopware-cms-v2-entity-architecture.md) - Database structure and entity definitions
- [Content System API Response Structure](./shopware-cms-v2.md) - API response format and structure