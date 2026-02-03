# Shopware 6 Inheritance System for Form Fields

## Introduction

### The Big Picture

Shopware 6 allows entities to have parent-child relationships. A **child entity** can inherit values from its **parent entity**, meaning it doesn't need to define its own value—it automatically uses the parent's. However, users can choose to **override** (or "remove inheritance") for any field, giving the child its own independent value.

This pattern is used extensively throughout Shopware:

| Use Case                 | Parent                  | Child                                 |
| ------------------------ | ----------------------- | ------------------------------------- |
| **Product Variants**     | Main product            | Variant (e.g., color/size variations) |
| **System Configuration** | Global settings         | Sales channel-specific settings       |
| **Theme Configuration**  | Base theme (Storefront) | Child/custom themes                   |
| **CMS Layouts**          | Layout default config   | Category/product page overrides       |
| **Custom Fields**        | Parent entity           | Child entity                          |

**Example scenario**: You have a T-Shirt product with variants for Small, Medium, and Large. All variants should inherit the description, manufacturer, and tax rate from the parent T-Shirt. But you want the Large variant to have a different price. The inheritance system allows the Large variant to override just the price while still inheriting everything else.

<!-- TODO: Add diagram showing parent product with arrows to child variants, some fields inherited (green), some overridden (orange) -->

---

## Understanding Form Fields

### What Are Form Fields?

In Shopware's Administration, form fields are Vue components that allow users to edit entity data. Common form field types include:

- **Text fields** (`mt-text-field`) - For names, descriptions, product numbers
- **Number fields** (`mt-number-field`) - For prices, quantities, weights
- **Select fields** (`sw-entity-single-select`) - For choosing related entities like manufacturers or tax rates
- **Switch fields** (`mt-switch`) - For boolean toggles like "active" or "highlight"
- **Text editors** (`sw-text-editor`) - For rich text content like product descriptions

### What Are Associations?

**Associations** are relationships between entities. In Shopware, these come in different types:

- **ManyToOne**: A product has one manufacturer (but a manufacturer can have many products)
- **OneToMany**: A product can have many prices (for different currencies/customer groups)
- **ManyToMany**: A product can have many categories, and categories can have many products

**In simple terms**: An association is essentially an array that can hold a list of related items. For example, a product's `categories` association is an array containing all the category entities that product belongs to. When you access `product.categories`, you get a collection (array-like) of category objects.

When we talk about "inheriting associations," we mean the child entity can use the parent's related entities. For example, a product variant can inherit its parent's category assignments.

**Important distinction for inheritance**:

- **Scalar values** (strings, numbers, booleans): Inherited when the child's value is `null` or `undefined`
- **Associations/Arrays**: Inherited when the child's collection is **empty** (length = 0)

---

## Quick Start: How to Use the Inheritance API

To make any form field support inheritance, you **wrap** it with the `sw-inherit-wrapper` component. This wrapper takes control of the value management and provides your inner form field with all the information and methods it needs to display and manage inheritance state.

### Basic Usage Pattern

The pattern works like this:

1. Wrap your form field with `<sw-inherit-wrapper>`
2. Place your actual form field inside the `#content` slot
3. The wrapper provides slot props that you need to **pass to your form field**

```html
<sw-inherit-wrapper
    v-model:value="product.name"
    :has-parent="!!parentProduct.id"
    :inherited-value="parentProduct.name"
    :label="$tc('sw-product.basicForm.labelTitle')"
>
    <template #content="props">
        <mt-text-field
            :model-value="props.currentValue"
            :is-inheritance-field="props.isInheritField"
            :is-inherited="props.isInherited"
            :disabled="props.isInherited"
            :label="props.label"
            @inheritance-restore="props.restoreInheritance"
            @inheritance-remove="props.removeInheritance"
            @update:model-value="props.updateCurrentValue"
        />
    </template>
</sw-inherit-wrapper>
```

The `#content` slot receives `props` from the wrapper. These props contain the **current display value** (`currentValue`), the **inheritance state** (`isInherited`, `isInheritField`), and **methods to control inheritance** (`restoreInheritance`, `removeInheritance`, `updateCurrentValue`). You need to wire these up to the corresponding props and events of your form field component.

#### How to Connect `currentValue` to Your Form Field

In a normal Vue form, you'd use `v-model` to bind a value to a form field:

```html
<!-- Normal v-model binding -->
<mt-text-field v-model="product.name" />
```

But with inheritance, **you cannot use `v-model` directly** on the entity property. Instead, the `sw-inherit-wrapper` manages the value, and you need to:

1. **Display** the value using `:model-value="props.currentValue"` (read)
2. **Update** the value using `@update:model-value="props.updateCurrentValue"` (write)

This is essentially the same as `v-model`, but split into its two parts, with the wrapper controlling the actual value that flows through. The wrapper decides whether `currentValue` shows the parent's value or the child's value based on inheritance state.

### Understanding the Props

| Prop               | Description                                                    |
| ------------------ | -------------------------------------------------------------- |
| `v-model:value`    | The child entity's actual stored value (see explanation below) |
| `:has-parent`      | Boolean indicating if a parent exists                          |
| `:inherited-value` | The parent's value to display/use when inherited               |
| `:label`           | Field label (displayed above the field)                        |
| `:help-text`       | Help text shown next to the label                              |

#### About `v-model:value` and `null` Values

The `v-model:value` binds to the **child entity's actual stored value** (e.g., `product.name`). This value can be `null`—and often **will be** `null` when the field is inherited.

**Why is `null` okay?** The wrapper computes a `currentValue` which is what actually gets displayed in the form:

- When `isInherited = true`: The form field shows `inheritedValue` (the parent's value), **not** the child's `null` value
- When `isInherited = false`: The form field shows the child's own `value`

So when a field is inherited, the user sees the parent's value in the form, not `null`. However, there's one exception: when inheritance is **forcefully removed** (using `forceInheritanceRemove`), the child explicitly has an empty/null value that is _not_ inherited. In this case, `currentValue` will be `null` or empty, and the form field will display that empty state.

**Database storage**: When a field is inherited, it's actually stored as `null` in the database. The backend understands this convention: if a child entity's field is `null` and the entity has a parent, the system knows to use the parent's value. This is how inheritance works at the data layer.

### Understanding the Slot Props

The `#content` slot receives these props from the wrapper:

| Slot Prop            | Description                                                         |
| -------------------- | ------------------------------------------------------------------- |
| `currentValue`       | The value to display (parent's if inherited, child's if overridden) |
| `isInheritField`     | Whether this field **can be** inherited (parent exists)             |
| `isInherited`        | Whether the field **is currently** inheriting                       |
| `updateCurrentValue` | Method to update the value                                          |
| `restoreInheritance` | Method to restore inheritance (set child value to `null`)           |
| `removeInheritance`  | Method to override inheritance (copy parent value to child)         |

<!-- TODO: Add annotated screenshot of a product form showing inherited fields vs overridden fields -->

---

## Core Components

### 1. `sw-inherit-wrapper` - The Main Wrapper

This component is the heart of the inheritance system. It:

1. Displays a label with the inheritance toggle (if the field has a parent)
2. Manages the logic for determining if a field is inherited
3. **Controls the value of the underlying form field** and manages the inheritance state
4. Provides slot props for the wrapped field to use
5. Handles restore/remove inheritance actions

[sw-inherit-wrapper/index.js](src/app/component/utils/sw-inherit-wrapper/index.js)

```html
{% block sw_inherit_wrapper %}
<div
    class="sw-inherit-wrapper"
    :class="{ 'is--inherited': isInherited, 'is--required': required, 'has--parent': hasParent }"
>
    <!-- Label row with inheritance toggle -->
    <template v-if="label">
        <div class="sw-inherit-wrapper__toggle-wrapper">
            <sw-inheritance-switch
                v-if="isInheritField"
                :is-inherited="isInherited"
                @inheritance-restore="restoreInheritance"
                @inheritance-remove="removeInheritance"
            />
            <label>{{ label }}</label>
        </div>
    </template>

    <!-- The actual form field goes here via slot -->
    <slot name="content" v-bind="{ currentValue, updateCurrentValue, isInherited, isInheritField, ... }"></slot>
</div>
{% endblock %}
```

**Key behavior**: When `isInheritField` is `true` (meaning a parent exists), the inheritance switch appears next to the label. When `isInheritField` is `false`, the field behaves like a normal field without any inheritance UI.

### 2. `sw-inheritance-switch` - The Toggle Button (Implementation Detail)

The `sw-inheritance-switch` is the visual toggle that allows users to switch between inherited and overridden states. While you don't typically use this component directly (the `sw-inherit-wrapper` includes it automatically), it's useful to understand how it works.

It shows:

- **Linked icon** 🔗 (`regular-link-horizontal`): Field is currently inheriting from parent
- **Unlinked icon** ⛓️‍💥 (`regular-link-horizontal-slash`): Field has been overridden

[sw-inheritance-switch/index.js](src/app/component/base/sw-inheritance-switch/index.js)

```html
{% block sw_inheritance_switch %}
<div class="sw-inheritance-switch">
    <!-- Shown when field IS inherited (linked to parent) -->
    <mt-icon
        v-if="isInherited"
        v-tooltip="'Click to override with your own value'"
        name="regular-link-horizontal"
        @click="onClickRemoveInheritance"
    />

    <!-- Shown when field is NOT inherited (has its own value) -->
    <mt-icon
        v-else
        v-tooltip="'Click to restore inherited value from parent'"
        name="regular-link-horizontal-slash"
        @click="onClickRestoreInheritance"
    />
</div>
{% endblock %}
```

#### Visual Styling

The inheritance system uses **purple theming** to indicate inherited state:

[sw-inherit-wrapper/sw-inherit-wrapper.scss](src/app/component/utils/sw-inherit-wrapper/sw-inherit-wrapper.scss)

```scss
.sw-inherit-wrapper {
    // When inherited, the label and toggle turn purple
    &.is--inherited {
        .sw-inherit-wrapper__toggle-wrapper {
            color: $color-module-purple-900;
        }
    }

    // Required fields show asterisk
    &.is--required .sw-inherit-wrapper__toggle-wrapper label::after {
        content: '*';
        color: $color-shopware-brand-500;
    }
}
```

<!-- TODO: Add side-by-side screenshots showing:
1. A field that is inherited (purple label, linked icon, maybe grayed out input)
2. A field that is overridden (normal label color, unlinked icon, editable input)
-->

---

## Terminology and Core Concepts

### Key Values

Understanding these three values is crucial:

| Term                 | What It Is                             | Example                                |
| -------------------- | -------------------------------------- | -------------------------------------- |
| **`value`**          | The child entity's actual stored value | `product.name` (could be `null`)       |
| **`inheritedValue`** | The parent entity's value              | `parentProduct.name` (e.g., "T-Shirt") |
| **`currentValue`**   | What gets displayed in the form        | Either `inheritedValue` or `value`     |

The wrapper computes `currentValue` like this:

[sw-inherit-wrapper/index.js](src/app/component/utils/sw-inherit-wrapper/index.js)

```javascript
currentValue: {
    get() {
        // If inherited, show parent's value. Otherwise, show child's value.
        return this.isInherited ? this.inheritedValue : this.value;
    },
    set(newValue) {
        // Logic to handle value changes...
    }
}
```

### `isInheritField` vs `isInherited`

These two properties are often confused but serve different purposes:

#### `isInheritField` - "Can this field be inherited?"

This determines whether the inheritance UI (toggle button) should appear at all.

```javascript
isInheritField() {
    // If hasParent is explicitly set, use that
    if (this.hasParent !== undefined) {
        return this.hasParent;
    }
    // Otherwise, check if an inherited value exists
    return !(this.inheritedValue === null || typeof this.inheritedValue === 'undefined');
}
```

- Returns `true` → Show the inheritance toggle
- Returns `false` → This is a standalone entity, no inheritance UI needed

#### `isInherited` - "Is this field currently inheriting?"

This determines the current inheritance state of the field.

```javascript
isInherited() {
    // No parent? Can't be inherited.
    if (!this.isInheritField || this.forceInheritanceRemove) {
        return false;
    }

    // Custom check function provided? Use it.
    if (typeof this.customInheritationCheckFunction === 'function') {
        return this.customInheritationCheckFunction(this.value);
    }

    // For associations/arrays: inherited if empty
    if ((this.isAssociation || Array.isArray(this.value)) && this.value) {
        return this.value.length <= 0;
    }

    // For scalars: inherited if null/undefined
    return this.value === null || this.value === undefined;
}
```

**Summary**:

- `isInheritField = true` + `isInherited = true` → Field is using parent's value
- `isInheritField = true` + `isInherited = false` → Field has its own override value
- `isInheritField = false` → No parent exists, normal field behavior

<!-- TODO: Add flowchart showing the decision tree for isInheritField and isInherited -->

### What Does "Remove Inheritance" Mean?

When a user clicks the unlink icon to "remove inheritance," they're saying: "I want this child to have its own value instead of using the parent's."

What happens technically:

1. The parent's value is **copied** to the child
2. The child's value is now non-null/non-empty
3. The `isInherited` computed property returns `false`
4. The field becomes editable

[sw-inherit-wrapper/index.js](src/app/component/utils/sw-inherit-wrapper/index.js)

```javascript
removeInheritance(newValue = this.currentValue) {
    // For associations: copy all items from parent to child
    if (this.isAssociation && newValue && this.value) {
        // Clear the child's collection
        this.restoreInheritance();

        // Copy each item from parent
        newValue.forEach((item) => {
            this.value.add(item);
        });

        this.updateValue(this.value, 'remove');
        return;
    }

    // For scalars: just set the value
    this.$emit('update:value', newValue);
}
```

### What Does "Restore Inheritance" Mean?

This is the opposite—the user wants to go back to using the parent's value.

What happens technically:

1. The child's value is set to `null` (for scalars) or emptied (for associations)
2. The `isInherited` computed property returns `true`
3. The field shows the parent's value and becomes disabled/read-only

```javascript
restoreInheritance() {
    this.forceInheritanceRemove = false;

    // For associations: remove all items from child's collection
    if (this.isAssociation) {
        this.value.getIds().forEach((id) => {
            this.value.remove(id);
        });
        this.updateValue(this.value, 'restore');
        return;
    }

    // For scalars: set to null
    this.$emit('update:value', null);
}
```

---

## Handling Special Cases

### Custom Inheritance Check Function

For simple fields, inheritance is determined by checking if the value is `null`/`undefined` (scalars) or empty (arrays). But some fields have complex data structures where this simple check doesn't work.

**Example: Product Prices**

A product's price isn't just a number—it's an array of price objects with currency, gross, net, list price, etc. An empty array would mean "inherited," but what if you genuinely want to set a price? You need a custom check.

```javascript
// In the price form component
inheritationCheckFunction() {
    // Inherited only if BOTH price and purchasePrices are empty
    return !this.prices.price.length && !this.prices.purchasePrices.length;
}
```

Usage in template:

```html
<sw-inherit-wrapper
    v-model:value="prices"
    :inherited-value="parentPrices"
    :custom-inheritation-check-function="inheritationCheckFunction"
>
    <!-- price fields -->
</sw-inherit-wrapper>
```

**Example: Theme Configuration**

Theme fields track inheritance state explicitly with an `isInherited` property:

```javascript
checkInheritanceFunction(fieldName) {
    return () => this.currentThemeConfig[fieldName].isInherited;
}
```

### Custom Remove Inheritance Function

When removing inheritance for complex objects, you might need special logic to properly copy the parent's value.

#### Why is special logic needed?

For simple scalar values like a product name, "removing inheritance" is straightforward: just copy the parent's string value to the child. But for complex objects like prices, the situation is more nuanced.

**Understanding Product Prices**

In Shopware, a product's price is not a simple number. Instead, it's an **array of price objects**, where each object represents the price for a specific currency. A typical price structure looks like:

```javascript
product.price = [
    {
        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca', // EUR
        gross: 19.99,
        net: 16.8,
        linked: true, // net is calculated from gross automatically
        listPrice: { gross: 24.99, net: 21.0, linked: true }, // strikethrough price
        regulationPrice: null, // for showing original price per legal requirements
    },
    {
        currencyId: '0192b6e8d4e07c14b1f852bcca152b58', // USD
        gross: 21.99,
        net: 18.48,
        linked: true,
    },
];
```

Additionally, products have `purchasePrices` with a similar structure. These are the **cost prices** (what you paid to acquire the product) used for calculating profit margins—not to be confused with the selling `price`.

**The Problem with Simple Copying**

When a variant removes price inheritance, you don't want to simply copy all currency prices from the parent. The variant might only need to set a price in the default currency initially. Also, prices contain nested objects (listPrice, regulationPrice) that need careful handling.

**Note on Association Behavior**: Since prices are stored as an association (array), the backend doesn't merge parent and child prices. When a child has its own prices array, it completely replaces the parent's—there's no automatic merging where child prices take precedence over parent prices for the same currency. You either inherit all prices from the parent (empty array) or have your own complete set of prices.

**The Solution: Custom Copy Logic**

[sw-product-price-form/index.js](src/module/sw-product/component/sw-product-price-form/index.js)

```javascript
removePriceInheritation(refPrice) {
    // Find only the default currency price from parent
    const defaultRefPrice = refPrice.price?.find(
        (price) => price.currencyId === this.defaultCurrency.id
    );

    const defaultRefPurchasePrice = refPrice.purchasePrices?.find(
        (price) => price.currencyId === this.defaultCurrency.id
    );

    // Create a new price array with just the default currency
    const prices = {
        price: [],
        purchasePrices: [],
    };

    if (defaultRefPrice) {
        prices.price.push({
            currencyId: defaultRefPrice.currencyId,
            gross: defaultRefPrice.gross,
            net: defaultRefPrice.net,
            linked: defaultRefPrice.linked,
            listPrice: defaultRefPrice.listPrice ? defaultRefPrice.listPrice : null,
            regulationPrice: defaultRefPrice.regulationPrice ? defaultRefPrice.regulationPrice : null,
        });
    }

    if (defaultRefPurchasePrice) {
        prices.purchasePrices.push({
            currencyId: defaultRefPurchasePrice.currencyId,
            gross: defaultRefPurchasePrice.gross,
            net: defaultRefPurchasePrice.net,
            linked: defaultRefPurchasePrice.linked,
        });
    }

    return prices;
}
```

This custom function ensures that when removing inheritance, the variant gets only the default currency price as a starting point, rather than all currency prices from the parent.

### Translated Fields

Shopware supports multiple languages, and translatable fields (like `name`, `description`) work differently than regular fields.

**The Challenge**: When you load a product in German, the `product.name` might show the German translation. But for inheritance, you need to get the inherited value **in the current language context**.

Shopware stores translations in a `translated` object on entities. This object contains the resolved translation for the current language context, with fallback chain applied.

**Example: How the `getInheritValue` function works**

[sw-product-basic-form/index.js](src/module/sw-product/component/sw-product-basic-form/index.js)

```javascript
getInheritValue(firstKey, secondKey) {
    const p = this.parentProduct;

    // Try to get the nested value first
    if (p[firstKey]) {
        return p[firstKey].hasOwnProperty(secondKey) ? p[firstKey][secondKey] : p[firstKey];
    }
    return null;
}
```

**Concrete example**: Let's say `parentProduct` looks like this:

```javascript
parentProduct = {
    id: '123',
    name: 'T-Shirt', // Raw value (system language)
    translated: {
        name: 'T-Shirt Blau', // Resolved translation for current language (German)
        description: 'Ein tolles blaues T-Shirt',
    },
};
```

When calling `getInheritValue('translated', 'name')`:

1. `p['translated']` exists, so we check if it has `'name'`
2. `p['translated'].hasOwnProperty('name')` is `true`
3. Returns `p['translated']['name']` → `'T-Shirt Blau'`

**Alternative case**: If you called `getInheritValue('translated', 'nonExistentField')`:

1. `p['translated']` exists
2. `p['translated'].hasOwnProperty('nonExistentField')` is `false`
3. Returns `p['translated']` → the entire translated object (fallback)

**Note**: This fallback behavior (returning the whole `translated` object) is a side effect of how the function is written, but it's **not an intended use case** for inheritance. In practice, you should always call `getInheritValue` with a valid field name that exists in the translated object. Returning the entire object would result in incorrect behavior in the UI.

**What the user sees**: When editing in German, the inheritance shows "T-Shirt Blau" from the parent. When switching to English, the user would see the English translation from the parent.

```html
<sw-inherit-wrapper
    v-model:value="product.name"
    :inherited-value="getInheritValue('translated', 'name')"
    :has-parent="!!parentProduct.id"
>
    <!-- The inherited value shows the parent's name in the current language -->
</sw-inherit-wrapper>
```

<!-- TODO: Add diagram showing entity with translated object structure -->

### Force Inheritance Remove (Empty Overrides)

**The Problem**: What if you want the child to have an explicitly empty value, not inherit the parent's value?

For example: Parent product has categories assigned. You want the child variant to have **no categories** (not inherit the parent's categories).

An empty array normally means "inherited," so how do you say "I explicitly want this empty"?

**The Solution**: `forceInheritanceRemove`

```javascript
removeInheritance(newValue = this.currentValue) {
    // If the new value is empty, force inheritance to be removed
    if (!newValue || (Array.isArray(newValue) && newValue.length <= 0)) {
        this.forceInheritanceRemove = true;
    }

    this.$emit('update:value', newValue);
}
```

When `forceInheritanceRemove` is `true`, the `isInherited` computed property returns `false` even if the value is empty:

```javascript
isInherited() {
    if (!this.isInheritField || this.forceInheritanceRemove) {
        return false;  // Not inherited, even if value is empty
    }
    // ... rest of checks
}
```

---

## Real-World Usage Examples

### Product Basic Form

[sw-product-basic-form.html.twig](src/module/sw-product/component/sw-product-basic-form/sw-product-basic-form.html.twig)

```html
<!-- Product name with translation inheritance -->
<sw-inherit-wrapper
    v-model:value="product.name"
    :has-parent="!!parentProduct.id"
    :inherited-value="getInheritValue('translated', 'name')"
>
    <template #content="props">
        <mt-text-field
            :model-value="props.currentValue"
            :is-inheritance-field="props.isInheritField"
            :is-inherited="props.isInherited"
            :disabled="props.isInherited || !allowEdit"
            :label="$tc('sw-product.basicForm.labelTitle')"
            @inheritance-restore="props.restoreInheritance"
            @inheritance-remove="props.removeInheritance"
            @update:model-value="props.updateCurrentValue"
        />
    </template>
</sw-inherit-wrapper>
```

### System Configuration (Sales Channel Inheritance)

System settings inherit from global (null sales channel) to specific sales channels:

[sw-system-config.html.twig](src/module/sw-settings/component/sw-system-config/sw-system-config.html.twig)

```html
<sw-inherit-wrapper
    v-model:value="actualConfigData[currentSalesChannelId][element.name]"
    :has-parent="isNotDefaultSalesChannel"
    :inherited-value="getInheritedValue(element)"
>
    <template #content="props">
        <sw-form-field-renderer
            :disabled="props.isInherited"
            :value="props.currentValue"
            @update:value="props.updateCurrentValue"
        />
    </template>
</sw-inherit-wrapper>
```

### Using `getBind()` Pattern with Form Field Renderer

When working with dynamic form fields (like custom fields), you often use the `sw-form-field-renderer` component. This component can render different field types based on a configuration object. The `getBind()` pattern is commonly used to prepare the props for this renderer.

**What is `sw-form-field-renderer`?**

The `sw-form-field-renderer` dynamically renders the appropriate form component based on a `type` and `config` prop. It's useful when you don't know the field type at compile time (e.g., custom fields defined by users).

[sw-form-field-renderer/index.js](src/app/component/form/sw-form-field-renderer/index.js)

**Key props for `sw-form-field-renderer`:**

| Prop     | Description                                                             |
| -------- | ----------------------------------------------------------------------- |
| `type`   | Field type: `'text'`, `'bool'`, `'int'`, `'datetime'`, `'select'`, etc. |
| `config` | Configuration object passed to the rendered component                   |
| `value`  | The current value                                                       |

**The `getBind()` Pattern**

When using `sw-form-field-renderer` with inheritance, you create a `getBind()` method that merges the field configuration with inheritance props:

[sw-custom-field-set-renderer/index.js](src/app/component/form/sw-custom-field-set-renderer/index.js)

```javascript
getBind(customField, props) {
    const customFieldClone = Shopware.Utils.object.cloneDeep(customField);

    // Add inheritance information to the config
    if (this.supportsMapInheritance(customFieldClone)) {
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

**Usage in template:**

```html
<sw-inherit-wrapper
    v-model:value="customFieldValues[customField.name]"
    :has-parent="hasParent"
    :inherited-value="getInheritedCustomField(customField.name)"
>
    <template #content="props">
        <sw-form-field-renderer
            v-bind="getBind(customField, props)"
            :value="props.currentValue"
            @update:value="props.updateCurrentValue"
        />
    </template>
</sw-inherit-wrapper>
```

### Why You Can't Just Spread Props

You might wonder if you can simplify the wiring by spreading all props directly:

```html
<!-- This does NOT work for inheritance! -->
<template #content="props">
    <my-field v-bind="props" />
</template>
```

Unfortunately, this doesn't work directly because:

1. The slot props have different names than what form fields expect (e.g., `currentValue` vs `modelValue`)
2. Events need to be explicitly wired up (`@update:model-value` → `props.updateCurrentValue`)
3. Different components expect different prop names for inheritance state

The explicit wiring, while verbose, ensures compatibility across different component types and makes the data flow clear.

---

## CMS Inherit Wrapper (Specialized)

For CMS layouts, there's a specialized `sw-cms-inherit-wrapper` that handles the unique inheritance model of CMS slot configurations.

CMS works differently:

- The **layout** defines default configuration for elements
- When a layout is assigned to a **category/product**, those can override specific slot configs
- Inheritance is stored in the entity's `slotConfig` property

[sw-cms-inherit-wrapper/index.ts](src/module/sw-cms/component/sw-cms-inherit-wrapper/index.ts)

```html
<sw-cms-inherit-wrapper
    :element="element"
    field="backgroundColor"
    :label="$tc('sw-cms.elements.image.labelBackgroundColor')"
>
    <template #default="{ isInherited }">
        <mt-colorpicker v-model="element.config.backgroundColor.value" :disabled="isInherited" />
    </template>
</sw-cms-inherit-wrapper>
```

This wrapper also includes a confirmation modal when restoring inheritance, since CMS changes can significantly affect the page appearance.

<!-- TODO: Add screenshot of CMS inheritance toggle with modal -->

---

## Summary: All Edge Cases

| Edge Case                             | How It's Handled                                      |
| ------------------------------------- | ----------------------------------------------------- |
| `null` or `undefined` value           | Considered inherited for scalar fields                |
| `false` value                         | Type-specific—`false` is a valid value, not inherited |
| Empty array/association               | Considered inherited for collection fields            |
| Empty array but want to override      | `forceInheritanceRemove = true`                       |
| Complex objects (prices, nested data) | Use `customInheritationCheckFunction`                 |
| Complex copying logic                 | Use `customRemoveInheritanceFunction`                 |
| Translated fields                     | Access via `translated` object on entity              |
| No parent exists                      | `isInheritField = false`, no toggle shown             |
| Deep object cloning                   | Use `deepCloneWithEntity()` utility                   |
| CMS slot config                       | Uses `slotConfig` on content entity                   |
| Theme fields                          | Tracks `isInherited` property per field               |

---

## Quick Reference

### Props for `sw-inherit-wrapper`

| Prop                               | Type     | Required | Description                                 |
| ---------------------------------- | -------- | -------- | ------------------------------------------- |
| `value`                            | any      | Yes      | The child entity's value (v-model)          |
| `inheritedValue`                   | any      | Yes      | The parent's value to inherit               |
| `hasParent`                        | Boolean  | No       | Override auto-detection of parent existence |
| `isAssociation`                    | Boolean  | No       | Whether value is an entity collection       |
| `label`                            | String   | No       | Field label (shows toggle when present)     |
| `helpText`                         | String   | No       | Help text next to label                     |
| `required`                         | Boolean  | No       | Show required asterisk                      |
| `disabled`                         | Boolean  | No       | Disable the toggle                          |
| `customInheritationCheckFunction`  | Function | No       | Custom `(value) => boolean` check           |
| `customRestoreInheritanceFunction` | Function | No       | Custom restore logic                        |
| `customRemoveInheritanceFunction`  | Function | No       | Custom remove logic                         |

### Slot Props for `#content`

| Prop                 | Type     | Description                              |
| -------------------- | -------- | ---------------------------------------- |
| `currentValue`       | any      | Value to display (parent's or child's)   |
| `isInheritField`     | Boolean  | Whether inheritance is possible          |
| `isInherited`        | Boolean  | Whether currently inheriting             |
| `updateCurrentValue` | Function | Update the value                         |
| `restoreInheritance` | Function | Restore to inherited state               |
| `removeInheritance`  | Function | Override with own value                  |
| `toggleInheritance`  | Function | Toggle between states                    |
| `error`              | Object   | Validation error object                  |
| `label`              | String   | The label (for use in nested components) |
