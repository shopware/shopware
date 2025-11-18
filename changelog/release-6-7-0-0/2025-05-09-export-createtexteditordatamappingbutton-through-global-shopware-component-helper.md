---
title: export createTextEditorDataMappingButton through global Shopware component helper
issue: #9012
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Added the `createTextEditorDataMappingButton` method to the global Shopware component helper.

___

# Upgrade Information

## Added the `createTextEditorDataMappingButton` method to the global Shopware component helper

 This change is a breaking change. The `createTextEditorDataMappingButton` method is now available through the global Shopware component helper.
    This method allows you to create a button that opens the text editor data mapping modal. The button can be used in your custom components to provide a consistent user experience when working with text editor data mapping.


## Example usage

```javascript
const { createTextEditorDataMappingButton } = Component.getComponentHelper();

const button = createTextEditorDataMappingButton({
    data: {
        text: 'Hello World',
        html: '<p>Hello World</p>'
    },
    onSave: (data) => {
        console.log('Data saved:', data);
    }
});
```
