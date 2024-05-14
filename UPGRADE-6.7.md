# 6.7.0.0
## Introduced in 6.6.2.0
## Removal of "sw-button":
The old "sw-button" component will be removed in the next major version. Please use the new "mt-button" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-button" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-button" with "mt-button".

Following changes are necessary:

### "sw-button" is removed
Replace all component names from "sw-button" with "mt-button"

Before:
```html
<sw-button>Save</sw-button>
```
After:
```html
<mt-button>Save</mt-button>
```

### "mt-button" has no value "ghost" in property "variant" anymore
Remove the property "variant". Use the property "ghost" instead.

Before:
```html
<sw-button variant="ghost">Save</sw-button>
```
After:
```html
<mt-button ghost>Save</mt-button>
```

### "mt-button" has no value "danger" in property "variant" anymore
Replace the value "danger" with "critical" in the property "variant".

Before:
```html
<sw-button variant="danger">Delete</sw-button>
```
After:
```html
<mt-button variant="critical">Delete</mt-button>
```

### "mt-button" has no value "ghost-danger" in property "variant" anymore
Replace the value "ghost-danger" with "critical" in the property "variant". Add the property "ghost".

Before:
```html
<sw-button variant="ghost-danger">Delete</sw-button>
```
After:
```html
<mt-button variant="critical" ghost>Delete</mt-button>
```

### "mt-button" has no value "contrast" in property "variant" anymore
Remove the value "contrast" from the property "variant". There is no replacement.

### "mt-button" has no value "context" in property "variant" anymore
Remove the value "context" from the property "variant". There is no replacement.

### "mt-button" has no property "router-link" anymore
Replace the property "router-link" with a "@click" event listener and a "this.$router.push()" method.

Before:
```html
<sw-button router-link="sw.example.route">Go to example</sw-button>
```
After:
```html
<mt-button @click="this.$router.push('sw.example.route')">Go to example</mt-button>
```
## Removal of deprecations
* Removed service `Shopware\Core\Content\MailTemplate\Service\AttachmentLoader` without replacement.
* Removed event `Shopware\Core\Content\MailTemplate\Service\Event\AttachmentLoaderCriteriaEvent` without replacement.
## Removal of "sw-icon":
The old "sw-icon" component will be removed in the next major version. Please use the new "mt-icon" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-icon" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-icon" with "mt-icon".

Following changes are necessary:

### "sw-icon" is removed
Replace all component names from "sw-icon" with "mt-icon"

Before:
```html
<sw-icon name="regular-times-s" />
```
After:
```html
<mt-icon name="regular-times-s" />
```

### "mt-icon" has no property "small" anymore
Replace the property "small" with "size" of value "16px" if used

Before:
```html
<sw-icon name="regular-times-s" small />
```
After:
```html
<mt-icon name="regular-times-s" size="16px" />
```

### "mt-icon" has no property "large" anymore
Replace the property "large" with "size" of value "32px" if used

Before:
```html
<sw-icon name="regular-times-s" large />
```

After:
```html
<mt-icon name="regular-times-s" size="32px" />
```

### "mt-icon" has different default sizes than "sw-icon"
If no property "size", "small" or "large" is used, you need to use the "size" prop with the value "24px" to avoid a different default size than with "sw-icon"

Before:
```html
<sw-icon name="regular-times-s" />
```
After:
```html
<mt-icon name="regular-times-s" size="24px" />
```
## Removal of "sw-card":
The old "sw-card" component will be removed in the next major version. Please use the new "mt-card" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-card" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-card" with "mt-card".

Following changes are necessary:

### "sw-card" is removed
Replace all component names from "sw-card" with "mt-card"

Before:
```html
<sw-card>Hello World</sw-card>
```
After:
```html
<mt-card>Hello World</mt-card>
```

### "mt-card" has no property "aiBadge" anymore
Replace the property "aiBadge" by using the "sw-ai-copilot-badge" component directly inside the "title" slot

Before:
```html
<mt-card aiBadge>Hello Wolrd</mt-card>
```

After:
```html
<mt-card>
    <slot name="title"><sw-ai-copilot-badge /></slot>
    Hello World
</mt-card>
```

### "mt-card" has no property "contentPadding" anymore
The property "contentPadding" is removed without a replacement.

Before:
```html
<mt-card contentPadding>Hello World</mt-card>
```

After:
```html
<mt-card>Hello World</mt-card>
```
## Removal of deprecated exceptions
* Removed `Shopware\Core\System\Snippet\Exception\FilterNotFoundException`. Use `Shopware\Core\System\Snippet\SnippetException::filterNotFound` instead.
* Removed `Shopware\Core\System\Snippet\Exception\InvalidSnippetFileException`. Use `Shopware\Core\System\Snippet\SnippetException::invalidSnippetFile` instead.
## Removal of "sw-text-field":
The old "sw-text-field" component will be removed in the next major version. Please use the new "mt-text-field" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-text-field" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-text-field" with "mt-text-field".

Following changes are necessary:

### "sw-text-field" is removed
Replace all component names from "sw-text-field" with "mt-text-field"

Before:
```html
<sw-text-field>Hello World</sw-text-field>
```
After:
```html
<mt-text-field>Hello World</mt-text-field>
```

### "mt-text-field" has no property "value" anymore
Replace all occurrences of the "value" prop with "modelValue"

Before:
```html
<mt-text-field value="Hello World" />
```
After:
```html
<mt-text-field modelValue="Hello World" />
```

### "mt-text-field" v-model:value is deprecated
Replace all occurrences of the "v-model:value" directive with "v-model"

Before:
```html
<mt-text-field v-model:value="myValue" />
```
After:
```html
<mt-text-field v-model="myValue" />
```

### "mt-text-field" has no property "size" with value "medium" anymore
Replace all occurrences of the "size" prop with "default"

Before:
```html
<mt-text-field size="medium" />
```
After:
```html
<mt-text-field size="default" />
```

### "mt-text-field" has no property "isInvalid" anymore
Remove all occurrences of the "isInvalid" prop

Before:
```html
<mt-text-field isInvalid />
```
After:
```html
<mt-text-field />
```

### "mt-text-field" has no property "aiBadge" anymore
Remove all occurrences of the "aiBadge" prop

Before:
```html
<mt-text-field aiBadge />
```
After:
```html
<mt-text-field />
```

### "mt-text-field" has no event "update:value" anymore
Replace all occurrences of the "update:value" event with "update:modelValue"

Before:
```html
<mt-text-field @update:value="updateValue" />
```

After:
```html
<mt-text-field @update:modelValue="updateValue" />
```

### "mt-text-field" has no event "base-field-mounted" anymore
Remove all occurrences of the "base-field-mounted" event

Before:
```html
<mt-text-field @base-field-mounted="onFieldMounted" />
```
After:
```html
<mt-text-field />
```

### "mt-text-field" has no slot "label" anymore
Remove all occurrences of the "label" slot. The slot content should be moved to the "label" prop. Only string values are supported. Other slot content is not supported
anymore.

Before:
```html
<mt-text-field>
    <template #label>
        My Label
    </template>
</mt-text-field>
```
After:
```html
<mt-text-field label="My label">
</mt-text-field>
```
## Removal of "sw-switch-field":
The old "sw-switch-field" component will be removed in the next major version. Please use the new "mt-switch" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-switch" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-switch-field" with "mt-switch".

Following changes are necessary:

### "sw-switch-field" is removed
Replace all component names from "sw-switch-field" with "mt-switch".

Before:
```html
<sw-switch-field>Hello World</sw-switch-field>
```
After:
```html
<mt-switch>Hello World</mt-switch>
```

### "mt-switch" has no "noMarginTop" prop anymore
Replace all occurrences of the "noMarginTop" prop with "removeTopMargin".

Before:
```html
<mt-switch noMarginTop />
```
After:
```html
<mt-switch removeTopMargin />
```

### "mt-switch" has no "size" prop anymore
Remove all occurrences of the "size" prop.

Before:
```html
<mt-switch size="small" />
```

After:
```html
<mt-switch />
```

### "mt-switch" has no "id" prop anymore
Remove all occurrences of the "id" prop.

Before:
```html
<mt-switch id="example-identifier" />
```

After:
```html
<mt-switch />
```

### "mt-switch" has no "value" prop anymore
Replace all occurrences of the "value" prop with "checked".

Before:
```html
<mt-switch value="true" />
```

After:
```html
<mt-switch checked="true" />
```

### "mt-switch" has no "ghostValue" prop anymore
Remove all occurrences of the "ghostValue" prop.

Before:
```html
<mt-switch ghostValue="true" />
```

After:
```html
<mt-switch />
```

### "mt-switch" has no "padded" prop anymore
Remove all occurrences of the "padded" prop. Use CSS styling instead.

Before:
```html
<mt-switch padded="true" />
```

After:
```html
<mt-switch />
```

### "mt-switch" has no "partlyChecked" prop anymore
Remove all occurrences of the "partlyChecked" prop.

Before:
```html
<mt-switch partlyChecked="true" />
```

After:
```html
<mt-switch />
```

### "mt-switch" has no "label" slot anymore
Replace all occurrences of the "label" slot with the "label" prop.

Before:
```html
<mt-switch>
    <template #label>
        Foobar
    </template>
</mt-switch>
```

After:
```html
<mt-switch label="Foobar">
</mt-switch>
```

### "mt-switch" has no "hint" slot anymore
Remove all occurrences of the "hint" slot.

Before:
```html
<mt-switch>
    <template #hint>
        Foobar
    </template>
</mt-switch>
```

After:
```html
<mt-switch>
    <!-- Slot "hint" was removed with no replacement. -->
</mt-switch>
```
## Removal of "sw-number-field":
The old "sw-number-field" component will be removed in the next major version. Please use the new "mt-number-field" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-number-field" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-number-field" with "mt-number-field".

Following changes are necessary:

### "sw-number-field" is removed
Replace all component names from "sw-number-field" with "mt-number-field"

Before:
```html
<sw-number-field />
```
After:
```html
<mt-number-field />
```

### "mt-number-field" has no property "value" anymore
Replace all occurrences of the "value" prop with "modelValue"

Before:
```html
<mt-number-field :value="5" />
```
After:
```html
<mt-number-field :modelValue="5" />
```

### "mt-number-field" v-model:value is deprecated
Replace all occurrences of the "v-model:value" directive with the combination of `:modelValue` and `@change`

Before:
```html
<mt-number-field v-model:value="myValue" />
```
After:
```html
<mt-number-field :modelValue="myValue" @change="myValue = $event" />
```

### "mt-number-field" label slot is deprecated
Replace all occurrences of the "label" slot with the "label" prop

Before:
```html
<mt-number-field>
    <template #label>
        My Label
    </template>
</mt-number-field>
```

After:
```html
<mt-number-field label="My Label" />
```

### "mt-number-field" update:value event is deprecated
Replace all occurrences of the "update:value" event with the "change" event

Before:
```html
<mt-number-field @update:value="updateValue" />
```
After:
```html
<mt-number-field @change="updateValue" />
```
## Removal of "sw-loader":
The old "sw-loader" component will be removed in the next major version. Please use the new "mt-loader" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-loader" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-loader" with "mt-loader".

Following changes are necessary:

### "sw-loader" is removed
Replace all component names from "sw-loader" with "mt-loader"

Before:
```html
<sw-loader />
```
After:
```html
<mt-loader />
```
## Removal of "sw-checkbox-field":
The old "sw-checkbox-field" component will be removed in the next major version. Please use the new "mt-checkbox" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-checkbox" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-checkbox-field" with "mt-checkbox".

Following changes are necessary:

### "sw-checkbox-field" is removed
Replace all component names from "sw-checkbox-field" with "mt-checkbox"

Before:
```html
<sw-checkbox-field />
```
After:
```html
<mt-checkbox />
```

### "mt-checkbox" has no property "value" anymore
Replace all occurrences of the "value" prop with "checked"

Before:
```html
<sw-checkbox-field :value="myValue" />
```
After:
```html
<mt-checkbox :checked="myValue" />
```

### "mt-checkbox" has changed the v-model usage
Replace all occurrences of the "v-model" directive with "v-model:checked"

Before:
```html
<sw-checkbox-field v-model="isCheckedValue" />
```
After:
```html
<mt-checkbox v-model:checked="isCheckedValue" />
```

### "mt-checkbox" has changed the slot "label" usage
Replace all occurrences of the "label" slot with the "label" prop

Before:
```html
<sw-checkbox-field>
    <template #label>
        Hello Shopware
    </template>
</sw-checkbox-field>
```

After:
```html
<mt-checkbox label="Hello Shopware">
</mt-checkbox>
```

### "mt-checkbox" has removed the slot "hint"
The "hint" slot was removed without replacement

Before:
```html
<sw-checkbox-field>
    <template v-slot:hint>
        Hello Shopware
    </template>
</sw-checkbox-field>
```

### "mt-checkbox" has removed the property "id"
The "id" prop was removed without replacement

Before:
```html
<sw-checkbox-field id="checkbox-id" />
```

### "mt-checkbox" has removed the property "ghostValue"
The "ghostValue" prop was removed without replacement

Before:
```html
<sw-checkbox-field ghostValue="yes" />
```

### "mt-checkbox" has changed the property "partlyChecked"
Replace all occurrences of the "partlyChecked" prop with "partial"

Before:
```html
<sw-checkbox-field partlyChecked />
```
After:
```html
<mt-checkbox partial />
```

### "mt-checkbox" has removed the property "padded"
The "padded" prop was removed without replacement

Before:
```html
<sw-checkbox-field padded />
```

### "mt-checkbox" has changed the event "update:value"
Replace all occurrences of the "update:value" event with "update:checked"

Before:
```html
<sw-checkbox-field @update:value="updateValue" />
```
After:
```html
<mt-checkbox @update:checked="updateValue" />
```

## Introduced in 6.6.1.0
## TreeUpdater::batchUpdate

We added a new optional parameter `bool $recursive` to `TreeUpdater::batchUpdate`.
If you extend the `TreeUpdater` class, you should properly handle the new parameter in your custom implementation.
```php
<?php

class CustomTreeUpdater extends TreeUpdater
{
    public function batchUpdate(array $updateIds, string $entity, Context $context, bool $recursive = false): void
    {
        parent::batchUpdate($updateIds, $entity, $context, $recursive);
    }
}
```
## \Shopware\Core\Framework\DataAbstractionLayer\Command\CreateSchemaCommand:
`\Shopware\Core\Framework\DataAbstractionLayer\Command\CreateSchemaCommand` will be removed. You can use `\Shopware\Core\Framework\DataAbstractionLayer\Command\CreateMigrationCommand` instead.

## \Shopware\Core\Framework\DataAbstractionLayer\SchemaGenerator:
`\Shopware\Core\Framework\DataAbstractionLayer\SchemaGenerator` will be removed. You can use `\Shopware\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator` instead.
## Replace `isEmailUsed` with `isEmailAlreadyInUse`:
* Replace `isEmailUsed` with `isEmailAlreadyInUse` in `sw-users-permission-user-detail`.


## Introduced in 6.6.0.0

## Replace `isEmailUsed` with `isEmailAlreadyInUse`:
* Replace `isEmailUsed` with `isEmailAlreadyInUse` in `sw-users-permission-user-detail`.

## AccountService refactoring

The `Shopware\Core\Checkout\Customer\SalesChannel\AccountService::login` method is removed. Use `AccountService::loginByCredentials` or `AccountService::loginById` instead.

Unused constant `Shopware\Core\Checkout\Customer\CustomerException::CUSTOMER_IS_INACTIVE` and unused method `Shopware\Core\Checkout\Customer\CustomerException::inactiveCustomer` are removed.
## Deprecated comparison methods:
* `floatMatch` and `arrayMatch` methods in `src/Core/Framework/Rule/CustomFieldRule.php` will be removed for Shopware 6.7.0.0

## Introduced in 6.5.7.0
## New `technicalName` property for payment and shipping methods
The `technicalName` property will be required for payment and shipping methods in the API.
The `technical_name` column will be made non-nullable for the `payment_method` and `shipping_method` tables in the database.

Plugin developers will be required to supply a `technicalName` for their payment and shipping methods.

Merchants must review their custom created payment and shipping methods for the new `technicalName` property and update their methods through the administration accordingly.
