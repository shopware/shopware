# Entity ID Guard Example

The `WeatherLoaderConfig` example in [custom-loaders.md](custom-loaders.md) holds plain strings, not entity ids, so it needs no id guard; its `WeatherApiClient` signals failure by returning `null` rather than throwing, so it needs no wrap either. A loader whose `PropertyReference` config key does resolve to an entity id, and whose collaborator throws, needs both checks below. Why the guard order and the broad catch: [Degradation boundary](../README.md#degradation-boundary).

A `PropertyReference` value arrives as whatever string the stored map holds, including an unsubstituted template placeholder such as `{{productId}}` left literal on a layout that never bound the property. `LoaderInputResolver::dereference()` only type-checks the value as a string, so a placeholder passes through untouched. Guard the value with `Uuid::isValid()` before using it as an id, and wrap the collaborator call in a `try`/`catch (ShopwareHttpException)`:

```php
/**
 * @extends AbstractContentDataLoader<SalesChannelProductEntity>
 */
final class ProductDetailLoader extends AbstractContentDataLoader
{
    public function __construct(private readonly AbstractProductDetailRoute $productRoute) {}

    public static function getRequirementType(): string
    { return 'my_product'; /* Must match serializer's getSource() */ }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'productId'),
        ]);
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        $productId = $inputs->stringOrNull('property');

        if ($productId === null) {
            return ContentDataLoaderResult::notFound();
        }

        $productId = u($productId)->lower()->toString();

        // Guard after the lowercase: Uuid::VALID_PATTERN is lowercase-only. An unsubstituted
        // placeholder fails this guard instead of the route's own id lookup.
        if (!Uuid::isValid($productId)) {
            return ContentDataLoaderResult::notFound();
        }

        try {
            $response = $this->productRoute->load($productId, $request, $context, new Criteria());
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        // A Store API route returns a StoreApiResponse that wraps the struct rather than being one, and
        // cachedExternally() takes a Struct, so unwrap the response before handing the value over.
        return ContentDataLoaderResult::cachedExternally($response->getProduct());
    }
}
```

Catch `ShopwareHttpException`, the single covering ancestor, never an enumerated union of the classes the chain appears to throw. Classes outside the boundary (`\TypeError`, `\JsonException`, Doctrine DBAL exceptions) propagate by design: degrading them blanks the element and hides a loader defect.
