---
title: Replace sw-text-editor with mt-text-editor
issue: NEXT-40024
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: @Jannis Leifeld
---
# Administration
* Changed i18n version to 10.0.5
* Added new text editor from Meteor component library
___
# Upgrade Information

## i18n Version Update

The i18n version has been updated to 10.0.5. This update introduce some breaking changes. Please refer to the [i18n changelog](https://vue-i18n.intlify.dev/guide/migration/breaking10.html) for more information.

## Replacing sw-text-editor with mt-text-editor

With version 6.8.0, we're replacing the deprecated `sw-text-editor` component with the new `mt-text-editor` from our Meteor component library. This guide will help you migrate your existing implementations.

### Component Documentation
For detailed documentation of the new `mt-text-editor` component, please refer to our [Meteor Component Library Documentation](https://meteor-component-library.vercel.app/?path=/docs/components-form-mt-text-editor--docs).

### Breaking Changes

#### 1. Props Changes
* `value` → `modelValue`: The prop for setting content has been renamed
  ```diff
  - <sw-text-editor :value="content" />
  + <mt-text-editor v-model="content" />
  ```

* `button-config` → `customButtons`: The configuration for custom buttons has been restructured
  ```diff
  - <sw-text-editor :button-config="buttonConfig" />
  + <mt-text-editor :custom-buttons="customButtons" />
  ```

* Removed props:
    - `vertical-align`: This prop is no longer supported
    - `allow-inline-data-mapping`: Data mapping functionality has been removed. You can add this button manually by using the `customButtons` prop and importing the `SwTextEditorToolbarButtonCmsDataMappingButton` from `src/app/component/meteor-wrapper/mt-text-editor/sw-text-editor-toolbar-button-cms-data-mapping`
    - `sanitize-input`, `sanitize-field-name`, `sanitize-info-warn`: Sanitization props are no longer needed as the new editor handles sanitization differently

#### 2. Events Changes
* `update:value` → `update:modelValue`: The event for content updates has been renamed
  ```diff
  - @update:value="onContentUpdate"
  + @update:modelValue="onContentUpdate"
  ```

#### 3. Toolbar Configuration
The button configuration structure has changed significantly. The new format uses TipTap's extension system:

```diff
- buttonConfig: [
-   {
-     type: 'bold',
-     icon: 'regular-bold-xs',
-     tag: 'b'
-   }
- ]
+ customButtons: [
+   {
+     name: 'custom-bold',
+     label: 'Bold',
+     icon: 'regular-bold-xs',
+     action: (editor) => editor.chain().focus().toggleBold().run()
+   }
+ ]
```

#### 4. Table Handling
Table functionality is now handled through TipTap's table extension. The custom table implementation from `sw-text-editor` has been replaced:
* Table resizing now uses TipTap's built-in table resize functionality
* Table-related methods like `setTableResizable` and `setTableListeners` are no longer needed

#### 5. Code View Changes
The code view toggle is now handled differently:
```diff
- <sw-text-editor ref="editor" :is-code-edit="isCodeEdit" />
+ <mt-text-editor ref="editor">
+   <!-- Code view is handled internally -->
+ </mt-text-editor>
```

### Migration Steps

1. Replace all instances of `sw-text-editor` with `mt-text-editor`
2. Update your v-model bindings to use the new prop name
3. Convert any custom button configurations to the new format
4. Remove any custom table handling code as it's now handled internally
5. Update any event listeners to use the new event names
6. Remove any sanitization-related code as it's handled internally
7. If you were using `vertical-align`, implement the alignment through CSS instead

### Example Migration

Here's a complete example of migrating a basic implementation:

```diff
- <sw-text-editor
-   :value="content"
-   :is-inline-edit="true"
-   :vertical-align="center"
-   :button-config="buttonConfig"
-   :sanitize-input="true"
-   @update:value="onContentUpdate"
- />

+ <mt-text-editor
+   v-model="content"
+   :is-inline-edit="true"
+   :custom-buttons="customButtons"
+   @update:modelValue="onContentUpdate"
+ />
```
