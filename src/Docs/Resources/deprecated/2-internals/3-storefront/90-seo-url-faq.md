[titleEn]: <>(SEO URL FAQ)
[hash]: <>(article:storefront_seo_urls_faq)

This document was created for Shopware 6.0.0 EA2

### Is there any way to change the seo urls in EA2?

EA2 introduces a new module to the administration which allows you to change the template after which the urls are generated.
The module is located at `Settings=>SEO`. 

The templates use the [Twig Template syntax](https://twig.symfony.com/).

Templates can be changed per sales channel using the dropdown at the top of the module. Using the "All Sales Channels" entry you can define
a default template which will be used if there is no specific template for a sales channel.

If you want to change the urls for a specific sales channel, you have to remove the inheritance by clicking on the purple chain.

### I've updated Shopware from a previous version to ea2, but the urls are still the same

During the update process all seo urls are created in a scheduled task. Scheduled tasks are processed asynchronously by a worker. 
The default worker is called `AdminWorker` and is executed only when a user enters the administration.
Please enter the administration or setup a [cli-worker](./../1-core/00-module/scheduled-tasks.md#the-task-scheduler).

If the problem still persists, you can force a recreation of all seo urls using a console command.
To do so, enter a shell inside your shopware directory and type
```bash
bin/console dal:refresh:index
```
After the command has finished the new seo urls will be used in the storefront immediately.

### I have changed my seo url template, but the seo urls remain unchanged

In EA2 seo urls are not recreated when the template changes. This behaviour is **not** final
and will most likely change in a upcoming version.

In the meantime you will have to manually recreate the seo urls using `dal:refresh:index`, as described above.

### I've changed the sales channel using the dropdown at the top of the seo module, but I cannot edit the template

You have to remove the inheritance from the `All Sales Channels` setting by clicking the purple chain.

### What happens to old urls if they are replaced by new ones?

Whenever you change the seo url of an entity, old urls are never deleted. Instead, the old urls
are flagged as such internally and will not be used to display content.  

Even though they are not broken. If you use use an old link to navigate to content which still exists it will be returned as usual.

### If old urls are not redirected, doesn't this lead to duplicate content?

No. Instead of using redirects Shopware 6.0.0 EA2 uses the canonical HTTP header or HTML link tag to inform search engines which url is canonical.
For more information, see [this post by Google](https://support.google.com/webmasters/answer/139066?hl=en#rel-canonical-header-method).
The method in use may change in the future.

### I've searched over the complete HTML and I cannot find the canonical tag!

Some browser do not include the `<head>` part in search queries when using the default browser tools (like the developer console).
You'll either have to use a diffrent editor or search manually. 

### I'd rather use a 301 HTTP code to redirect old seo urls

This feature is not availabe in Shopware 6.0.0 EA2, but may be implemented in future versions.

### I want to assign url to a specific product

The ability to override seo urls on a per product/ per category basis has not made it into the administration with EA2. We expect it to arrive with the next release.

Even though the technical basis is already there and can be used by developers.

