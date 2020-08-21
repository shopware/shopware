<!--
Thank you for contributing to Shopware! Please fill out this description template to help us to process your pull request.

Please make sure to fulfil our contribution guideline (https://docs.shopware.com/en/shopware-platform-dev-en/contribution/contribution-guideline?category=shopware-platform-dev-en/contribution).

Do your changes need to be mentioned in the documentation?
Add notes on your change right now in the documentation files in /src/Docs/Resources and add them to the pull request as well. 
-->

### 1. Why is this change necessary? 

Elasticsearch indexing will fail when we have product custom fields of type select, and the plugin EnpterpriseSearchPlatform is installed.

### 2. What does this change do, exactly?

The fix will set the right type for this kind of custom field, will not lead to the default one, which is JsonField. Please check 
Shopware\Core\System\CustomField\CustomFieldService::getCustomField method.

### 3. Describe each step to reproduce the issue or behaviour.

- Create a product custom field of type select with some options
````
$this->container->get('custom_field_set.repository')->upsert([
[
                'id' => md5('field_of_type_select_set'),
                'name' => 'field_of_type_select_set',
                'config' => [
                    'label' => [
                        'en-GB' => 'field_of_type_select set',
                        'de-DE' => "field_of_type_select set",
                    ]
                ],
                'customFields' => [
                    [
                        'id' => md5('field_of_type_select'),
                        'name' => 'field_of_type_select',
                        'type' => CustomFieldTypes::SELECT,
                        'config' => [
                            'componentName' => 'sw-single-select',
                            'customFieldType' => 'select',
                            'customFieldPosition' => 1,
                            'validation' => 'required',
                            'label' => [
                                'en-GB' => 'field_of_type_select'
                            ],
                            'placeholder' => [
                                'en-GB' => 'Select...'
                            ],
                            'options' => [
                                [
                                    'label' => [
                                        'en-GB' => 'Option1'
                                    ],
                                    'value' => 'Option1'
                                ],
                                [
                                    'label' => [
                                        'en-GB' => 'Option2'
                                    ],
                                    'value' => 'Option2'
                                ],                                
                            ]
                        ]
                    ]
                ],
                'relations' => [                    
                    [
                        'id' => md5('field_of_type_select_set_product'),
                        'entityName' => $this->container->get(ProductDefinition::class)->getEntityName()
                    ]
                ]
            ]
], $context);
````
- Install and activate EnterpriseSearch plugin
- Run bin/console dbal:refresh:index in order to index the product entities into ES. On running, you will get the error:
 ````
object mapping for [customFields.field_of_type_select] tried to parse field [field_of_type_select] as object, but found a concrete value
````
This is because the mapping in ES for custom field of type select gets the default field type Json and not the correct one, LongTextField or StringField, because we are sending a concrete value not an object. 

### 4. Please link to the relevant issues (if any).


### 5. Checklist

- [ ] I have written tests and verified that they fail without my change
- [x] I have squashed any insignificant commits
- [ ] I have written or adjusted the documentation according to my changes
- [ ] This change has comments for package types, values, functions, and non-obvious lines of code
- [ ] I have read the contribution requirements and fulfil them.
