---
title: Add product name and manufacturer logo block
issue: NEXT-11502
---
# Administration
* Changed method `registerCmsBlock()` in `src/module/sw-cms/service/cms.service.js` to activate or deactivate registering a cms block.
* Changed method `registerCmsElement()` in `src/module/sw-cms/service/cms.service.js` to activate or deactivate registering a cms element.
* Changed method `onWizardComplete()` in `src/module/sw-cms/page/sw-cms-create/index.js` to trigger method `onPageTypeChange()` if page type is product detail.
* Changed block `{% block sw_cms_slot_element_modal_selection_element %}{% endblock %}` in `src/module/sw-cms/component/sw-cms-slot/sw-cms-slot.html.twig`.
* Added component `product-heading` in `src/module/sw-cms/blocks`.
* Added component `product-name` in `src/module/sw-cms/elements`.
* Added component `manufacturer-logo` in `src/module/sw-cms/elements`.
