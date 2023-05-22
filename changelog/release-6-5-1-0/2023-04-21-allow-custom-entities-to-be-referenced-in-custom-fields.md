---
title: Allow custom entities to be referenced in custom fields
issue: NEXT-22759
---
# Core
* Added `custom-fields-aware` boolean flag to `entity` xml definition so that custom entities can opt in to be referenced via custom fields.
* Added required `label-property` attribute to `entity` xml definition when the `custom-fields-aware` flag is set to true, to specify which field should be used as a label in the administration when selecting the custom entity via a custom field.
___
# Administration
* Changed the `sw-custom-field-type-entity` component to add custom entities when they have the `custom-fields-aware` flag set to true with the corresponding label property.

