[titleEn]: <>(Composite Data Loading)
[hash]: <>(article:storefront_data_loading)

Composite data loading describes the process of preparing and fetching data for a whole template page worth of content. As a progressive web application the page rendering process is a central concern of the storefront. Contrary to solutions through `postDispatch`-Handling or `lazy loading`  from templates the controller actions of the storefront do a full lookup and handle data loading transparently and fully. The storefront provides a general solution for this problem - the **Page System**

### A single page

A single page is always a three class namespace. There is the `Page`-Struct ([`\Shopware\Storefront\Framework\Page\GenericPage`](https://github.com/shopware/platform/blob/master/src/Storefront/Framework/Page/GenericPage.php)), representing the data. The `PageLoader` ([`\Shopware\Storefront\Framework\Page\PageLoaderInterface`](https://github.com/shopware/platform/blob/master/src/Storefront/Framework/Page/PageLoaderInterface.php)) handling creation of page structs and the `PageEvent` ([`\Shopware\Core\Framework\Event\NestedEvent`](https://github.com/shopware/platform/blob/master/src/Core/Framework/Event/NestedEvent.php)) adding a clean extension point to pages. For the address page this looks like this:

![page classes](./dist/page-class.png)

### Composition

Of course the address page from above needs multiple data structures to load and prepare a single page. Composition is handled through the page loaders themselves, by triggering loading of associated data internally. A full representation of the address page with header and footer looks like this:

![page loader classes](./dist/page-loader-classes.png)

That results in this structure:

![page classes](./dist/page-classes.png)

The sequence in which loading is triggered is this:

![page load sequence](./dist/page-load-sequence.png)

### Pagelet

The pages in the storefront can roughly be categorized into pages and pagelets. Although functionally identical they represent different usages of the pages data. A page is generally rendered into a full template, whereas a pagelet is either a part of a page or accessible through an xhr route, sometimes even both. 
