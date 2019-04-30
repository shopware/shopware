[titleEn]: <>(Attributes are now custom fields)

We have got the feedback that the intended usage of attributes is unclear
and often mistaken for product properties. So we decided to rename 
attributes to custom fields.

## What do I have to do?
If you've used `AttributesField` in  definitions, you need to replace 
it with `CustomFields` and rename the column `attributes` to `custom_fields`.
Alternatively, you can override the `storageName` in the `CustomFields` constructor.
