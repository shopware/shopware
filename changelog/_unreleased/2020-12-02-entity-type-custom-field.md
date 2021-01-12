---
title: Entity Type Custom Field
issue: NEXT-12269
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com 
author_github: lernhart
---
# Core
* Added class `Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\SingleEntitySelectField` to allow custom field generation via an app's manifest.xml.
* Added class `Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\MultiEntitySelectField` to allow custom field generation via an app's manifest.xml.
* Added constant `Shopware\Core\System\CustomField\CustomFieldTypes::ENTITY`
* Changed content of `src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd` to restrict manifest's content to work with the new custom field types.
___
# Administration
* Added `sw-custom-field-type-entity` component to allow the creation of entity type custom fields.
* Added method `displayLabelProperty` in `src/Administration/Resources/app/administration/src/app/component/form/select/entity/sw-entity-multi-select` to allow single as well as multiple properties to display a label.
* Added method `displayLabelProperty` in `src/Administration/Resources/app/administration/src/app/component/form/select/entity/sw-entity-single-select` to allow single as well as multiple properties to display a label.
* Changed `src/Administration/Resources/app/administration/src/app/component/form/select/entity/sw-entity-multi-select/sw-entity-multi-select.html.twig` to adapt to the new labelProperty display.
* Changed `src/Administration/Resources/app/administration/src/app/component/form/select/entity/sw-entity-multi-select/sw-entity-single-select.html.twig` to adapt to the new labelProperty display.
* Changed criteria in `src/Administration/app/administration/src/app/component/form/select/entity/sw-entity-single-select` to correctly fetch inherited data
* Changed criteria in `src/Administration/app/administration/src/app/component/form/select/entity/sw-entity-multi-select` to correctly fetch inherited data
