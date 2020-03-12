[titleEn]: <>(Service Tags)
[hash]: <>(article:service_tags)

Service tags in `Shopware` are the same as [Symfony - Service Tags](https://symfony.com/doc/current/service_container/tags.html).
They are used to register your service in some special way. 

## Shopware Service Tags
Below you can find a listing of each service tag that exists in `Shopware`.
Some tags are links and will provide you with further information.

| Tag                                                                        | Required Arguments     | Usage                                                              | Interface                                                           |
|----------------------------------------------------------------------------|------------------------|--------------------------------------------------------------------|---------------------------------------------------------------------|
| [shopware.entity.definition](./20-data-abstraction-layer/070-definition.md)| *entity*               | This tag is used to make your entities system-wide available       | \Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition      |
| shopware.feature                                                           | *flag*                 | This tag is used internally as a feature flag for VCS              |                                                                     |
| shopware.filesystem.factory                                                |                        | This tag is used to register a new FilesystemFactory for Flysystem | \Shopware\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface |
| [shopware.cart.collector](./../../4-how-to/230-cart-add-discount.md)       |                        | This tag is used to register a CartCollector                       | \Shopware\Core\Checkout\Cart\CollectorInterface                     |
| shopware.cart.validator                                                    |                        | This tag is used to register a CartValidator                       | \Shopware\Core\Checkout\Cart\CartValidatorInterface                 |
| shopware.composite_search.definition                                       | *priority*             | Used to mark a entity as searchable via the composite Search       | \Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition      |
| shopware.legacy_encoder                                                    |                        | Used to register a new legacy passwordEncoder, to support migrating Customers | \Shopware\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface |
| shopware.entity_indexer                                                    |                        | Used to register a new Indexer                                     | \Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer |
| shopware.cms.data_resolver                                                 |                        | Used to register a new Data Resolver for CMS blocks                | \Shopware\Core\Content\Cms\SlotDataResolver\SlotTypeDataResolverInterface |
| shopware.pathname.strategy                                                 |                        | Used to register a new Strategy for generating Pathnames           | \Shopware\Core\Content\Media\Pathname\PathnameStrategy\PathnameStrategyInterface |
| [shopware.scheduled.task](./../../4-how-to/100-scheduled-tasks.md)         |                        | Used to register a new ScheduledTask                               | \Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask                |
| shopware.oauth.scope                                                       |                        | Used to add a new Scope for the OAuth authentification             | \League\OAuth2\Server\Entities\ScopeEntityInterface                 |
| shopware.search_analyzer                                                   |                        | Used to add a new SearchAnalyzer                                   | \Shopware\Core\Framework\Search\Util\SearchAnalyzerInterface        |
| shopware.field_resolver                                                    |                        | Used to add a new FieldResolver                                    | \Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverInterface |
| shopware.field_accessor_builder                                            |                        | Used to add a new FieldAccessorBuilder                             | \Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderInterface |
| shopware.field_serializer                                                  |                        | Used to add a new FieldSerializer                                  | \Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface |
| shopware.demodata_generator                                                |                        | Used to add a new Demodata Generator                               | \Shopware\Core\Framework\Demodata\DemodataGeneratorInterface        |
| shopware.snippet.file                                                      |                        | Used to add a new SnippetFile                                      | \Shopware\Core\System\Snippet\Files\SnippetFileInterface         |
| shopware.snippet.filter                                                    |                        | Used to add a new SnippetFilter                                    | \Shopware\Core\System\Snippet\Filter\SnippetFilterInterface      |
| shopware.value_generator_connector                                         |                        | Used to add a new NumberRange -> Storage Connector                 | \Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageInterface |
| shopware.value_generator_pattern                                           |                        | Used to add a new NumberRange pattern                              | \Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternInterface |
| [shopware.entity.extension](./../../4-how-to/180-entity-extension.md)      |                        | Used to add an Extension to an EntityDefinition                    | \Shopware\Core\Framework\DataAbstractionLayer\EntityExtension |
| shopware.seo_url.generator                                                 |                        | Used to add a new SeoUrl Generator                                 | \Shopware\Storefront\Framework\Seo\SeoUrlGenerator\SeoUrlGeneratorInterface |
| [shopware.rule.definition](./../../4-how-to/210-custom-rule.md)            |                        | Used to add a new Rule                                             | \Shopware\Core\Framework\Rule\Rule                                  |
| [shopware.payment.method.sync](./../../4-how-to/010-payment-plugin.md)     |                        | Used to add a synchronous PaymentMethod                            | \Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface |
| [shopware.payment.method.async](./../../4-how-to/010-payment-plugin.md)    |                        | Used to add a asynchronous PaymentMethod                           | \Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface |
| shopware.metadata.loader                                                   |                        | Used to add a new Media MetadataLoader                             | \Shopware\Core\Content\Media\Metadata\MetadataLoader\MetadataLoaderInterface |
| shopware.media_type.detector                                               |                        | Used to add a new MediaType Detector                               | \Shopware\Core\Content\Media\TypeDetector\TypeDetectorInterface     |
