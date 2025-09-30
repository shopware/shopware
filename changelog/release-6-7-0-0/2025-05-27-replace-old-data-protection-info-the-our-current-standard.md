---
title: Replace old data protection info the our current standard
issue: #8388
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: @Marcel Brode
---
# Administration
* Changed the following files to adjust the CMS forms' data protection information to reflect the storefront adjustments:
  * `sw-cms/elements/form/component/templates/form-contact/sw-cms-el-form-contact.html.twig`
  * `sw-cms/elements/form/component/templates/form-contact/sw-cms-el-form-newsletter.html.twig`
___
# Storefront
* Changed CMS forms to use a general data protection information block instead of an outdated one, explicitly for the CMS
* Deprecated old data protection information blocks, so that `cms-element-form/form-components/cms-element-form-privacy.html.twig` can be deleted:
  * `cms_form_privacy_opt_in`
  * `cms_form_privacy_opt_in_title`
