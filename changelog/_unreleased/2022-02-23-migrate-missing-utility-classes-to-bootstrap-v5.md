---
title: Migrate missing utility classes to Bootstrap v5
issue: NEXT-15229
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Storefront
* Added global twig variables in `\Shopware\Storefront\Framework\Twig\TemplateDataExtension::getGlobals`
    * Added variable `paddingStartClass` to replace Bootstrap utility class `pl` with `ps`
    * Added variable `paddingEndClass` to replace Bootstrap utility class `pr` with `pe`
    * Added variable `marginStartClass` to replace Bootstrap utility class `ml` with `ms`
    * Added variable `marginEndClass` to replace Bootstrap utility class `mr` with `me`
* Changed variable `breakpoint` and added missing `xl`, `xxl` breakpoints and match all breakpoint keys with their corresponding breakpoint from `theme_config` in the following blocks/templates:
    * `single_cms_page_script_breakpoints` in template `Resources/views/storefront/page/content/single-cms-page.html.twig`
    * `error_maintenance_script_breakpoints` in template `Resources/views/storefront/page/error/error-maintenance.html.twig`
