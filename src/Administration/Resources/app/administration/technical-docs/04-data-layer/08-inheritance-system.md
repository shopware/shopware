# Inheritance System

The inheritance system in Shopware 6 Administration enables form fields to inherit values from parent entities. This document explains how inheritance works using form fields and custom fields as practical examples.

## Overview

Shopware 6 entities can have parent-child relationships where child entities inherit field values from their parents. The Administration provides UI components that visualize this inheritance state and allow users to override or restore inherited values.

### Common Inheritance Scenarios

| Use Case                 | Parent                  | Child                                 |
| ------------------------ | ----------------------- | ------------------------------------- |
| **Product Variants**     | Main product            | Variant (e.g., color/size variations) |
| **System Configuration** | Global settings         | Sales channel-specific settings       |
| **Theme Configuration**  | Base theme (Storefront) | Child/custom themes                   |
| **Custom Fields**        | Parent entity           | Child entity                          |

### How Inheritance Works

```
┌─────────────────────────────────────────────────────────────┐
│  Parent Product                                             │
│  ┌─────────────────┐  ┌─────────────────┐                   │
│  │ name: "T-Shirt" │  │ price: €19.99   │                   │
│  └─────────────────┘  └─────────────────┘                   │
└─────────────────────────────────────────────────────────────┘
          │                      │
          │ inherited            │ overridden
          ▼                      ▼
┌─────────────────────────────────────────────────────────────┐
│  Child Variant (Large)                                      │
│  ┌─────────────────┐  ┌─────────────────┐                   │
│  │ name: null      │  │ price: €24.99   │                   │
│  │ (shows parent)  │  │ (own value)     │                   │
│  └─────────────────┘  └─────────────────┘                   │
└─────────────────────────────────────────────────────────────┘
```

**Inheritance rules:**
- **Scalar values** (strings, numbers, booleans): Inherited when the child's value is `null` or `undefined`
- **Associations/Arrays**: Inherited when the child's collection is empty (length = 0)

## The `sw-inherit-wrapper` Component

The `sw-inherit-wrapper` component is the foundation of inheritance UI. It wraps form fields and provides:

1. Visual indication of inheritance state (purple styling when inherited)
2. Toggle button to switch between inherited and overridden states
3. Slot props for the wrapped field to consume

### Basic Usage Pattern

```html
<sw-inherit-wrapper
    v-model:value="product.name"
    :has-parent="!!parentProduct.id"
    :inherited-value="parentProduct.translated.name"
    :label="$tc('sw-product.basicForm.labelName')"
>
    <template #content="props">
        <mt-text-field
            :model-value="props.currentValue"
            :is-inheritance-field="props.isInheritField"
            :is-inherited="props.isInherited"
            :disabled="props.isInherited"
            @inheritance-restore="props.restoreInheritance"
            @inheritance-remove="props.removeInheritance"
            @update:model-value="props.updateCurrentValue"
        />
    </template>
</sw-inherit-wrapper>
```

### Wrapper Props

| Prop                               | Type     | Required | Description                                 |
| ---------------------------------- | -------- | -------- | ------------------------------------------- |
| `value`                            | any      | Yes      | The child entity's value (v-model)          |
| `inheritedValue`                   | any      | Yes      | The parent's value to inherit               |
| `hasParent`                        | Boolean  | No       | Whether a parent exists for inheritance     |
| `isAssociation`                    | Boolean  | No       | Whether value is an entity collection       |
| `label`                            | String   | No       | Field label (shows toggle when present)     |
| `helpText`                         | String   | No       | Help text next to label                     |
| `customInheritationCheckFunction`  | Function | No       | Custom `(value) => boolean` check           |
| `customRemoveInheritanceFunction`  | Function | No       | Custom logic when removing inheritance      |

### Slot Props

The `#content` slot receives these props:

| Prop                 | Type     | Description                              |
| -------------------- | -------- | ---------------------------------------- |
| `currentValue`       | any      | Value to display (parent's or child's)   |
| `isInheritField`     | Boolean  | Whether inheritance is possible          |
| `isInherited`        | Boolean  | Whether currently inheriting             |
| `updateCurrentValue` | Function | Update the value                         |
| `restoreInheritance` | Function | Restore to inherited state               |
| `removeInheritance`  | Function | Override with own value                  |

## Custom Fields with Inheritance

Custom fields support inheritance through the `sw-custom-field-set-renderer` component. When a parent entity exists, custom field values can be inherited.

### How Custom Field Inheritance Works

```html
<!-- sw-custom-field-set-renderer.html.twig -->
<sw-inherit-wrapper
    v-model:value="customFields[customField.name]"
    v-bind="getInheritWrapperBind(customField)"
    :has-parent="hasParent"
    :inherited-value="getInheritedCustomField(customField.name)"
>
    <template #content="props">
        <sw-form-field-renderer
            v-bind="getBind(customField, props)"
            :value="props.currentValue"
            v-on="getElementEventListeners(customField, props)"
            @update:value="props.updateCurrentValue"
        />
    </template>
</sw-inherit-wrapper>
```

### The `getBind()` Pattern

The `getBind()` method prepares props for the form field renderer, including inheritance information:

```javascript
getBind(customField, props) {
    const customFieldClone = Shopware.Utils.object.cloneDeep(customField);

    if (this.supportsMapInheritance(customFieldClone)) {
        // Pass inheritance props through mapInheritance
        customFieldClone.mapInheritance = props;

        // For Meteor components, also set direct props
        if (this.isMeteorComponent(customField)) {
            customFieldClone.isInheritanceField = props.isInheritField;
            customFieldClone.isInherited = props.isInherited;
            customFieldClone.inheritanceRemove = props.removeInheritance;
            customFieldClone.inheritanceRestore = props.restoreInheritance;
            customFieldClone.inheritedValue = props.currentValue;
        }

        return customFieldClone;
    }

    return customFieldClone;
}
```

### Getting Inherited Custom Field Values

```javascript
getInheritedCustomField(customFieldName) {
    // Get value from parent's translated custom fields
    const value = this.parentEntity?.translated?.customFields?.[customFieldName] ?? null;

    if (value) {
        return value;
    }

    // Return type-appropriate default if no parent value
    const customFieldType = this.getCustomFieldInformation(customFieldName).type;

    switch (customFieldType) {
        case 'select': return [];
        case 'bool': return false;
        case 'html':
        case 'datetime':
        case 'text': return '';
        case 'float':
        case 'int': return 0;
        default: return null;
    }
}
```

## Inheritance Exceptions: Switch and Checkbox

The `mt-switch` and `mt-checkbox` components implement their own inheritance toggle **inside** the component. This creates an exception to the standard wrapper pattern.

### Why These Components Are Different

Standard form fields display the inheritance toggle in the wrapper's label area:

```
┌────────────────────────────────────────────────┐
│  🔗 Product Name            [Help Text]        │  ← Wrapper toggle
├────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────┐  │
│  │ T-Shirt                                  │  │  ← Input field
│  └──────────────────────────────────────────┘  │
└────────────────────────────────────────────────┘
```

But switches and checkboxes have limited vertical space. Placing the toggle above would look awkward, so these components include the inheritance switch **inline**:

```
┌────────────────────────────────────────────────┐
│  🔗 ◯━━━━━━● Active Product                    │  ← Toggle built into switch
└────────────────────────────────────────────────┘
```

### How the Exception Is Implemented

When the wrapper detects a Meteor component (switch/checkbox), it **hides its own toggle, label, and help text**:

```javascript
// sw-custom-field-set-renderer
getInheritWrapperBind(customField) {
    if (this.supportsMapInheritance(customField)) {
        return {};  // Empty = no label/helpText from wrapper
    }

    return {
        helpText: this.getInlineSnippet(customField.config.helpText) || '',
        label: this.getInlineSnippet(customField.config.label) || ' ',
    };
}
```

Similarly in `sw-system-config`:

```javascript
getInheritWrapperBind(element) {
    if (this.hasMapInheritanceSupport(element)) {
        return {};
    }

    if (this.isMeteorComponent(element)) {
        return {};  // Meteor components handle their own inheritance UI
    }

    return {
        label: this.getInlineSnippet(element.config.label),
        helpText: this.getInlineSnippet(element.config.helpText),
    };
}
```

### Passing Inheritance Props to Meteor Components

Instead of using the wrapper's UI, inheritance props are passed directly to the component:

```javascript
// For Meteor components (mt-switch, mt-checkbox, etc.)
if (isMeteorComponent) {
    customFieldClone.isInheritanceField = props.isInheritField;
    customFieldClone.isInherited = props.isInherited;
    customFieldClone.inheritanceRemove = props.removeInheritance;
    customFieldClone.inheritanceRestore = props.restoreInheritance;
    customFieldClone.inheritedValue = props.currentValue;
}
```

The component then renders its own inheritance switch and handles the toggle events internally.

### Affected Components

Components that implement their own inheritance switch:

| Component       | Type     | Notes                                    |
| --------------- | -------- | ---------------------------------------- |
| `mt-switch`     | Boolean  | Switch toggle with inline inheritance    |
| `mt-checkbox`   | Boolean  | Checkbox with inline inheritance         |

## Understanding Inheritance State

### `isInheritField` vs `isInherited`

These two properties serve different purposes:

**`isInheritField`**: Can this field be inherited?

```javascript
isInheritField() {
    // Use explicit hasParent if provided
    if (this.hasParent !== undefined) {
        return this.hasParent;
    }
    // Otherwise check if inherited value exists
    return !(this.inheritedValue === null || typeof this.inheritedValue === 'undefined');
}
```

**`isInherited`**: Is this field currently inheriting?

```javascript
isInherited() {
    if (!this.isInheritField || this.forceInheritanceRemove) {
        return false;
    }

    // Custom check function if provided
    if (typeof this.customInheritationCheckFunction === 'function') {
        return this.customInheritationCheckFunction(this.value);
    }

    // Arrays: inherited if empty
    if ((this.isAssociation || Array.isArray(this.value)) && this.value) {
        return this.value.length <= 0;
    }

    // Scalars: inherited if null/undefined
    return this.value === null || this.value === undefined;
}
```

### State Combinations

| `isInheritField` | `isInherited` | Behavior                              |
| ---------------- | ------------- | ------------------------------------- |
| `true`           | `true`        | Field uses parent's value (disabled)  |
| `true`           | `false`       | Field has its own override value      |
| `false`          | N/A           | No parent exists, normal field        |

## Inheritance Actions

### Remove Inheritance

When a user clicks the unlink icon, the parent's value is copied to the child:

```javascript
removeInheritance(newValue = this.currentValue) {
    // Handle custom function
    if (typeof this.customRemoveInheritanceFunction === 'function') {
        this.updateValue(this.customRemoveInheritanceFunction(newValue, this.value), 'remove');
        return;
    }

    // Handle associations
    if (this.isAssociation && newValue && this.value) {
        this.restoreInheritance();
        if (newValue.length <= 0) {
            this.forceInheritanceRemove = true;
        }
        newValue.forEach((item) => {
            this.value.add(item);
        });
        this.updateValue(this.value, 'remove');
        return;
    }

    // Handle empty values
    if (!newValue || (Array.isArray(newValue) && newValue.length <= 0)) {
        this.forceInheritanceRemove = true;
    }

    this.$emit('update:value', newValue);
}
```

### Restore Inheritance

When restoring, the child's value is set to `null` (or emptied for associations):

```javascript
restoreInheritance() {
    this.forceInheritanceRemove = false;

    // Handle custom function
    if (typeof this.customRestoreInheritanceFunction === 'function') {
        this.updateValue(this.customRestoreInheritanceFunction(this.value), 'restore');
        return;
    }

    // Handle associations
    if (this.isAssociation) {
        this.value.getIds().forEach((id) => {
            this.value.remove(id);
        });
        this.updateValue(this.value, 'restore');
        return;
    }

    // Scalars: set to null
    this.$emit('update:value', null);
}
```

## Custom Inheritance Check Functions

For complex data structures, provide a custom check function:

```javascript
// Example: Price fields with nested structure
inheritationCheckFunction() {
    return !this.prices.price.length && !this.prices.purchasePrices.length;
}
```

```html
<sw-inherit-wrapper
    v-model:value="prices"
    :inherited-value="parentPrices"
    :custom-inheritation-check-function="inheritationCheckFunction"
>
    <!-- price fields -->
</sw-inherit-wrapper>
```

## Force Inheritance Remove

Sometimes you want a child to have an explicitly empty value (not inherit the parent's). Use `forceInheritanceRemove`:

```javascript
// When removing inheritance with an empty value
if (!newValue || (Array.isArray(newValue) && newValue.length <= 0)) {
    this.forceInheritanceRemove = true;
}
```

This ensures `isInherited` returns `false` even when the value is empty.

## Visual Styling

Inherited fields use purple theming to indicate inheritance state:

```scss
.sw-inherit-wrapper {
    &.is--inherited {
        .sw-inherit-wrapper__toggle-wrapper {
            color: $color-module-purple-900;
        }
    }
}
```

The inheritance switch displays:
- **Linked icon** 🔗: Field is inheriting from parent
- **Unlinked icon** ⛓️‍💥: Field has been overridden

## Best Practices

1. **Always provide `hasParent`**: Explicitly set whether a parent exists rather than relying on auto-detection
2. **Use translated values**: For translatable fields, get the inherited value from `parentEntity.translated`
3. **Handle complex types**: Use custom check/remove functions for nested data structures
4. **Test edge cases**: Verify behavior with `null`, empty arrays, and `false` boolean values

## Quick Reference

### Wrapper Events

| Event                | Description                          |
| -------------------- | ------------------------------------ |
| `update:value`       | Emitted when value changes           |
| `inheritance-restore`| Emitted when inheritance is restored |
| `inheritance-remove` | Emitted when inheritance is removed  |

### Edge Cases

| Scenario                      | Behavior                                      |
| ----------------------------- | --------------------------------------------- |
| `null` or `undefined` value   | Considered inherited for scalar fields        |
| `false` boolean value         | Valid value, **not** inherited                |
| Empty array/association       | Considered inherited for collection fields    |
| Empty array + forceRemove     | Explicitly empty, **not** inherited           |
