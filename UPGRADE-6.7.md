# 6.7.0.0
## Introduced in 6.6.5.0
## Payment: Reworked payment handlers
* The payment handlers have been reworked to provide a more flexible and consistent way to handle payments.
* The new `AbstractPaymentHandler` class should be used to implement payment handlers.
* The following interfaces have been deprecated:
  * `AsyncPaymentHandlerInterface`
  * `PreparedPaymentHandlerInterface`
  * `SyncPaymentHandlerInterface`
  * `RefundPaymentHandlerInterface`
  * `RecurringPaymentHandlerInterface`
* Synchronous and asynchronous payments have been merged to return an optional redirect response.


## Payment: Capture step of prepared payments removed
* The method `capture` has been removed from the `PreparedPaymentHandler` interface. This method is no longer being called for apps.
* Use the `pay` method instead for capturing previously validated payments.

## App System: Payment: payment states
* For asynchronous payments, the default payment state `unconfirmed` was used for the `pay` call and `paid` for `finalized`. This is no longer the case. Payment states are no longer set by default.

## App system: Payment:  finalize step
* The `finalize` step now transmits the `queryParameters` under the object key `requestData` as other payment calls
## Customer: Default payment method removed
* Removed default payment method from customer entity, since it was mostly overriden by old saved contexts
* Logic is now more consistent to always be the last used payment method

## Rule builder: Condition `customerDefaultPaymentMethod` removed
* Removed condition `customerDefaultPaymentMethod` from rule builder, since customers do not have default payment methods anymore
* Existing rules with this condition will be automatically migrated to the new condition `paymentMethod`, so the currently selected payment method

## Flow builder: Trigger `checkout.customer.changed-payment-method` removed
* Removed trigger `checkout.customer.changed-payment-method` from flow builder, since customers do not have default payment methods anymore
* Existing flows will be automatically disabled with Shopware 6.7 and removed in a future, destructive migration
## Removal of sw-dashboard-statistics and associated component sections and data sets
The component `sw-dashboard-statistics` (`src/module/sw-dashboard/component/sw-dashboard-statistics`) has been removed without replacement.

The associated component sections `sw-chart-card__before` and `sw-chart-card__after` were removed, too.
Use `sw-dashboard__before-content` and `sw-dashboard__after-content` instead.

Before:
```js
import { ui } from '@shopware-ag/meteor-admin-sdk';

ui.componentSection.add({
    positionId: 'sw-chart-card__before',
    ...
})
```

After:
```js
import { ui } from '@shopware-ag/meteor-admin-sdk';

ui.componentSection.add({
    positionId: 'sw-dashboard__before-content',
    ...
})
```

Additionally, the associated data sets `sw-dashboard-detail__todayOrderData` and `sw-dashboard-detail__statisticDateRanges` were removed.
In both cases, use the Admin API instead.
## Direct debit default payment: State change removed
* The default payment method "Direct debit" will no longer automatically change the order state to "in progress". Use the flow builder instead, if you want the same behavior.

## Introduced in 6.6.4.0
## Removal of Storefront `sw-skin-alert` SCSS mixin
The mixin `sw-skin-alert` will be removed in v6.7.0. Instead of styling the alert manually with CSS selectors and the custom mixin `sw-skin-alert`,
we modify the appearance inside the `alert-*` modifier classes directly with the Bootstrap CSS variables like it is documented: https://getbootstrap.com/docs/5.3/components/alerts/#sass-loops

Before:
```scss
@each $color, $value in $theme-colors {
  .alert-#{$color} {
    @include sw-skin-alert($value, $white);
  }
}
```

After:
```scss
@each $state, $value in $theme-colors {
  .alert-#{$state} {
    --#{$prefix}alert-border-color: #{$value};
    --#{$prefix}alert-bg: #{$white};
    --#{$prefix}alert-color: #{$body-color};
  }
}
```

## Removal of Storefront alert class `alert-has-icon` styling
When rendering an alert using the include template `Resources/views/storefront/utilities/alert.html.twig`, the class `alert-has-icon` will be removed. Helper classes `d-flex align-items-center` will be used instead.

```diff
- <div class="alert alert-info alert-has-icon">
+ <div class="alert alert-info d-flex align-items-center">
    {% sw_icon 'info' %}
    <div class="alert-content-container">
        An important info
    </div>
</div>
```

## Removal of Storefront alert inner container `alert-content`
As of v6.7.0, the superfluous inner container `alert-content` will be removed to have lesser elements and be more aligned with Bootstraps alert structure.
When rendering an alert using the include template `Resources/views/storefront/utilities/alert.html.twig`, the inner container `alert-content` will no longer be present in the HTML output.

The general usage of `Resources/views/storefront/utilities/alert.html.twig` and all include parameters remain the same.

Before:
```html
<div role="alert" class="alert alert-info d-flex align-items-center">
    <span class="icon icon-info"><svg></svg></span>                                                    
    <div class="alert-content-container">
        <div class="alert-content">                                                    
            Your shopping cart is empty.
        </div>                
    </div>
</div>
```

After:
```html
<div role="alert" class="alert alert-info d-flex align-items-center">
    <span class="icon icon-info"><svg></svg></span>                                                    
    <div class="alert-content-container">
        Your shopping cart is empty.
    </div>
</div>
```
## Removal of "sw-popover":
The old "sw-popover" component will be removed in the next major version. Please use the new "mt-floating-ui" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-floating-ui" component. This component is much different from the old "sw-popover" component, so the codemod will not be able to convert all occurrences. You will have to manually adjust some parts of your codebase. For this you can look at the Storybook documentation for the Meteor Component Library.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-popover" with "mt-floating-ui".

Following changes are necessary:

### "sw-popover" is removed
Replace all component names from "sw-popover" with "mt-floating-ui"

Before:
```html
<sw-popover />
```
After:
```html
<mt-floating-ui />
```

### "mt-floating-ui" has no property "zIndex" anymore
The property "zIndex" is removed without a replacement.

Before:
```html
<sw-popover :zIndex="myZIndex" />
```
After:
```html
<mt-floating-ui />
```

### "mt-floating-ui" has no property "resizeWidth" anymore
The property "resizeWidth" is removed without a replacement.

Before:
```html
<sw-popover :resizeWidth="myWidth" />
```

After:
```html
<mt-floating-ui />
```

### "mt-floating-ui" has no property "popoverClass" anymore
The property "popoverClass" is removed without a replacement.

Before:
```html
<sw-popover popoverClass="my-class" />
```
After:
```html
<mt-floating-ui />
```

### "mt-floating-ui" is not open by default anymore
The "open" property is removed. You have to control the visibility of the popover by yourself with the property "isOpened".

Before:
```html
<sw-popover />
```
After:
```html
<mt-floating-ui :isOpened="myVisibility" />
```
## Removal of deprecations
* Removed method `ImportExportProfileEntity::getName()` and `ImportExportProfileEntity::setName()`. Use `getTechnicalName()` and `setTechnicalName()` instead.
* Removed `profile` attribute from `ImportEntityCommand`. Use `--profile-technical-name` instead.
* Removed `name` field from `ImportExportProfileEntity`.
## All Vuex stores will be transitioned to Pinia
* All Shopware states will become Pinia Stores and will be available via `Shopware.Store`

## Introduced in 6.6.3.0
## onlyAvailable flag removed
* The `onlyAvailable` flag in the `Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute` in the request will be removed in the next major version. The route will always filter the payment and shipping methods before calling the checkout gateway based on availability.
## AbstractCartOrderRoute::order method signature change
* The `Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute::order` method will change its signature in the next major version. A new mandatory `request` parameter will be introduced.
## Shopware config changes:
### cart
Replace the `redis_url` parameter in `config/packages/shopware.yaml` file:
```yaml
    cart:
        compress: false
        expire_days: 120
        redis_url: false # or 'redis://localhost'
```
to
```yaml
    cart:
        compress: false
        expire_days: 120
        storage:
            type: "mysql" # or "redis"
            # config:
                # dsn: 'redis://localhost'
```
### number_range
Replace the `redis_url` parameter in `config/packages/shopware.yaml` file:
```yaml
    number_range:
        increment_storage: "SQL"
        redis_url: false # or 'redis://localhost'
```
to
```yaml
    number_range:
        increment_storage: "mysql" # or "redis"
        # config:
            # dsn: 'redis://localhost'
```
## Removal of deprecations
* Removed constants `Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig::{ACTION_NAME,MAIL_CONFIG_EXTENSION}` use `Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction::{ACTION_NAME,MAIL_CONFIG_EXTENSION}` instead
* Removed constant `Shopware\Core\Content\MailTemplate\MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION` use `Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction::ACTION_NAME` instead
* Removed class `Shopware\Core\Content\MailTemplate\MailTemplateActions` without replacement
## Removal of "sw-tabs":
The old "sw-tabs" component will be removed in the next major version. Please use the new "mt-tabs" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-tabs" component. In this specific component it cannot convert anything correctly, because the new "mt-tabs" component has a different API. You have to manually check and solve every "TODO" comment created by the codemod.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-tabs" with "mt-tabs".

Following changes are necessary:

### "sw-tabs" is removed
Replace all component names from "sw-tabs" with "mt-tabs"

Before:
```html
<sw-tabs />
```
After:
```html
<mt-tabs />
```

### "sw-tabs" wrong "default" slot usage will be replaced with "items" property
You need to replace the "default" slot with the "items" property. The "items" property is an array of objects which are used to render the tabs. Using the "sw-tabs-item" component is not needed anymore.

Before:
```html
<sw-tabs>
    <template #default="{ active }">
        <sw-tabs-item name="tab1">Tab 1</sw-tabs-item>
        <sw-tabs-item name="tab2">Tab 2</sw-tabs-item>
    </template>
</sw-tabs>
```

After:
```html
<mt-tabs :items="[
    {
        'label': 'Tab 1',
        'name': 'tab1'
    },
    {
        'label': 'Tab 2',
        'name': 'tab2'
    }
]">
</mt-tabs>
```

### "sw-tabs" wrong "content" slot usage - content should be set manually outside the component
The content slot is not supported anymore. You need to set the content manually outside the component. You can use the "new-item-active" event to get the active item and set it to a variable. Then you can use this variable anywere in your template.

Before:
```html
<sw-tabs>
    <template #content="{ active }">
        The current active item is {{ active }}
    </template>
</sw-tabs>
```

After:
```html
<!-- setActiveItem need to be defined -->
<mt-tabs @new-item-active="setActiveItem"></mt-tabs>

The current active item is {{ activeItem }}
```

### "sw-tabs" property "isVertical" was renamed to "vertical"
Before:
```html
<sw-tabs is-vertical />
```

After:
```html
<mt-tabs vertical />
```

### "sw-tabs" property "alignRight" was removed
Before:
```html
<sw-tabs align-right />
```

After:
```html
<mt-tabs />
```
## Removal of "sw-select-field":
The old "sw-select-field" component will be removed in the next major version. Please use the new "mt-select" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-select" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-select-field" with "mt-select".

Following changes are necessary:

### "sw-select-field" is removed
Replace all component names from "sw-select-field" with "mt-select"

Before:
```html
<sw-select-field />
```
After:
```html
<mt-select />
```

### "sw-select-field" prop "value" was renamed to "modelValue"
Replace all occurrences of the prop "value" with "modelValue"

Before:
```html
<sw-select-field :value="selectedValue" />
```

After:
```html
<mt-select :modelValue="selectedValue" />
```

### "sw-select-field" the "v-model:value" was renamed to "v-model"
Replace all occurrences of the "v-model:value" directive with "v-model"

Before:
```html
<sw-select-field v-model:value="selectedValue" />
```

After:
```html
<mt-select v-model="selectedValue" />
```

### "sw-select-field" the prop "options" expect a different format
The prop "options" now expects an array of objects with the properties "label" and "value". The old format with "name" and "id" is not supported anymore.

Before:
```html
<sw-select-field :options="[ { name: 'Option 1', id: 1 }, { name: 'Option 2', id: 2 } ]" />
```

After:
```html
<mt-select :options="[ { label: 'Option 1', value: 1 }, { label: 'Option 2', value: 2 } ]" />
```

### "sw-select-field" the prop "aside" was removed
The prop "aside" was removed without replacement.

Before:
```html
<sw-select-field :aside="true" />
```

After:
```html
<mt-select />
```

### "sw-select-field" the default slot was removed
The default slot was removed. The options are now passed via the "options" prop.

Before:
```html
<sw-select-field>
    <option value="1">Option 1</option>
    <option value="2">Option 2</option>
</sw-select-field>
```

After:
```html
<mt-select :options="[ { label: 'Option 1', value: 1 }, { label: 'Option 2', value: 2 } ]" />
```

### "sw-select-field" the label slot was removed
The label slot was removed. The label is now passed via the "label" prop.

Before:
```html
<sw-select-field>
    <template #label>
        My Label
    </template>
</sw-select-field>
```

After:
```html
<mt-select label="My Label" />
```

### "sw-select-field" the event "update:value" was renamed to "update:modelValue"
The event "update:value" was renamed to "update:modelValue"

Before:
```html
<sw-select-field @update:value="onUpdateValue" />
```

After:
```html
<mt-select @update:modelValue="onUpdateValue" />
```
## Removal of "sw-textarea-field":
The old "sw-textarea-field" component will be removed in the next major version. Please use the new "mt-textarea" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-textarea" component. In this specific component it cannot convert anything correctly, because the new "mt-textarea" component has a different API. You have to manually check and solve every "TODO" comment created by the codemod.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-textarea-field" with "mt-textarea".

Following changes are necessary:

### "sw-textarea-field" is removed
Replace all component names from "sw-textarea-field" with "mt-textarea"

Before:
```html
<sw-textarea-field />
```
After:
```html
<mt-textarea />
```

### "sw-textarea-field" property "value" is replaced by "modelValue"
Replace all occurrences of the property "value" with "modelValue"

Before:
```html
<sw-textarea-field :value="myValue" />
```
After:
```html
<mt-textarea :modelValue="myValue" />
```

### "sw-textarea-field" binding "v-model:value" is replaced by "v-model"
Replace all occurrences of the binding "v-model:value" with "v-model"

Before:
```html
<sw-textarea-field v-model:value="myValue" />
```

After:
```html
<mt-textarea v-model="myValue" />
```

### "sw-textarea-field" slot "label" is replaced by property "label"
Replace all occurrences of the slot "label" with the property "label"

Before:
```html
<sw-textarea-field>
    <template #label>
        My Label
    </template>
</sw-textarea-field>
```

After:
```html
<mt-textarea label="My Label" />
```

### "sw-textarea-field" event "update:value" is replaced by "update:modelValue"
Replace all occurrences of the event "update:value" with "update:modelValue"

Before:
```html
<sw-textarea-field @update:value="onUpdateValue" />
```

After:
```html
<mt-textarea @update:modelValue="onUpdateValue" />
```
## Removal of "sw-datepicker":
The old "sw-datepicker" component will be removed in the next major version. Please use the new "mt-datepicker" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-datepicker" component. In this specific component it cannot convert anything correctly, because the new "mt-datepicker" component has a different API. You have to manually check and solve every "TODO" comment created by the codemod.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-datepicker" with "mt-datepicker".

Following changes are necessary:

### "sw-datepicker" is removed
Replace all component names from "sw-datepicker" with "mt-datepicker"

Before:
```html
<sw-datepicker />
```
After:
```html
<mt-datepicker />
```

### "sw-datepicker" property "value" is replaced by "modelValue"
Replace all occurrences of the property "value" with "modelValue"

Before:
```html
<sw-datepicker :value="myValue" />
```
After:
```html
<mt-datepicker :modelValue="myValue" />
```

### "sw-datepicker" binding "v-model:value" is replaced by "v-model"
Replace all occurrences of the binding "v-model:value" with "v-model"

Before:
```html
<sw-datepicker v-model:value="myValue" />
```

After:
```html
<mt-datepicker v-model="myValue" />
```

### "sw-datepicker" slot "label" is replaced by property "label"
Replace all occurrences of the slot "label" with the property "label"

Before:
```html
<sw-datepicker>
    <template #label>
        My Label
    </template>
</sw-datepicker>
```

After:
```html
<mt-datepicker label="My Label" />
```

### "sw-datepicker" event "update:value" is replaced by "update:modelValue"
Replace all occurrences of the event "update:value" with "update:modelValue"

Before:
```html
<sw-datepicker @update:value="onUpdateValue" />
```

After:
```html
<mt-datepicker @update:modelValue="onUpdateValue" />
```
## Removal of "sw-password-field":
The old "sw-password-field" component will be removed in the next major version. Please use the new "mt-password-field" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-password-field" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-password-field" with "mt-password-field".

Following changes are necessary:

### "sw-password-field" is removed
Replace all component names from "sw-password-field" with "mt-password-field"

Before:
```html
<sw-password-field>Hello World</sw-password-field>
```
After:
```html
<mt-password-field>Hello World</mt-password-field>
```

### "mt-password-field" has no property "value" anymore
Replace all occurrences of the "value" prop with "modelValue"

Before:
```html
<sw-password-field value="Hello World" />
```
After:
```html
<mt-password-field modelValue="Hello World" />
```

### "mt-password-field" v-model:value is deprecated
Replace all occurrences of the "v-model:value" directive with "v-model"

Before:
```html
<sw-password-field v-model:value="myValue" />
```
After:
```html
<mt-password-field v-model="myValue" />
```

### "mt-password-field" has no property "size" with value "medium" anymore
Replace all occurrences of the "size" prop with "default"

Before:
```html
<sw-password-field size="medium" />
```
After:
```html
<mt-password-field size="default" />
```

### "mt-password-field" has no property "isInvalid" anymore
Remove all occurrences of the "isInvalid" prop

Before:
```html
<sw-password-field isInvalid />
```
After:
```html
<mt-password-field />
```

### "mt-password-field" has no event "update:value" anymore
Replace all occurrences of the "update:value" event with "update:modelValue"

Before:
```html
<sw-password-field @update:value="updateValue" />
```

After:
```html
<mt-password-field @update:modelValue="updateValue" />
```

### "mt-password-field" has no event "base-field-mounted" anymore
Remove all occurrences of the "base-field-mounted" event

Before:
```html
<sw-password-field @base-field-mounted="onFieldMounted" />
```
After:
```html
<mt-password-field />
```

### "mt-password-field" has no slot "label" anymore
Remove all occurrences of the "label" slot. The slot content should be moved to the "label" prop. Only string values are supported. Other slot content is not supported
anymore.

Before:
```html
<sw-password-field>
    <template #label>
        My Label
    </template>
</sw-password-field>
```
After:
```html
<mt-password-field label="My label">
</mt-password-field>
```

### "mt-password-field" has no slot "hint" anymore
Remove all occurrences of the "hint" slot. The slot content should be moved to the "hint" prop. Only string values are supported. Other slot content is not supported

Before:
```html
<sw-password-field>
    <template #hint>
        My Hint
    </template>
</sw-password-field>
```
After:
```html
<mt-password-field hint="My hint">
</mt-password-field>
```
## Removal of "sw-colorpicker":
The old "sw-colorpicker" component will be removed in the next major version. Please use the new "mt-colorpicker" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-colorpicker" component. In this specific component it cannot convert anything correctly, because the new "mt-colorpicker" component has a different API. You have to manually check and solve every "TODO" comment created by the codemod.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-colorpicker" with "mt-colorpicker".

Following changes are necessary:

### "sw-colorpicker" is removed
Replace all component names from "sw-colorpicker" with "mt-colorpicker"

Before:
```html
<sw-colorpicker />
```
After:
```html
<mt-colorpicker />
```

### "sw-colorpicker" property "value" is replaced by "modelValue"
Replace all occurrences of the property "value" with "modelValue"

Before:
```html
<sw-colorpicker :value="myValue" />
```
After:
```html
<mt-colorpicker :modelValue="myValue" />
```

### "sw-colorpicker" binding "v-model:value" is replaced by "v-model"
Replace all occurrences of the binding "v-model:value" with "v-model"

Before:
```html
<sw-colorpicker v-model:value="myValue" />
```

After:
```html
<mt-colorpicker v-model="myValue" />
```

### "sw-colorpicker" slot "label" is replaced by property "label"
Replace all occurrences of the slot "label" with the property "label"

Before:
```html
<sw-colorpicker>
    <template #label>
        My Label
    </template>
</sw-colorpicker>
```

After:
```html
<mt-colorpicker label="My Label" />
```

### "sw-colorpicker" event "update:value" is replaced by "update:modelValue"
Replace all occurrences of the event "update:value" with "update:modelValue"

Before:
```html
<sw-colorpicker @update:value="onUpdateValue" />
```

After:
```html
<mt-colorpicker @update:modelValue="onUpdateValue" />
```
## Removal of "sw-external-link":
The old "sw-external-link" component will be removed in the next major version. Please use the new "mt-external-link" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-external-link" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-external-link" with "mt-external-link".

Following changes are necessary:

### "sw-external-link" is removed
Replace all component names from "sw-external-link" with "mt-external-link"

Before:
```html
<sw-external-link>Hello World</sw-external-link>
```
After:
```html
<mt-external-link>Hello World</mt-external-link>
```

### "sw-external-link" property "icon" is removed
The "icon" property is removed from the "mt-external-link" component. There is no replacement for this property.

Before:
```html
<sw-external-link icon="world">Hello World</sw-external-link>
```
After:
```html
<mt-external-link>Hello World</mt-external-link>
```
## Removal of "sw-skeleton-bar":
The old "sw-skeleton-bar" component will be removed in the next major version. Please use the new "mt-skeleton-bar" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-skeleton-bar" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-skeleton-bar" with "mt-skeleton-bar".

Following changes are necessary:

### "sw-skeleton-bar" is removed
Replace all component names from "sw-skeleton-bar" with "mt-skeleton-bar"

Before:
```html
<sw-skeleton-bar>Hello World</sw-skeleton-bar>
```
After:
```html
<mt-skeleton-bar>Hello World</mt-skeleton-bar>
```
## Removal of "sw-email-field":
The old "sw-email-field" component will be removed in the next major version. Please use the new "mt-email-field" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-email-field" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-email-field" with "mt-email-field".

Following changes are necessary:

### "sw-email-field" is removed
Replace all component names from "sw-email-field" with "mt-email-field"

Before:
```html
<sw-email-field>Hello World</sw-email-field>
```
After:
```html
<mt-email-field>Hello World</mt-email-field>
```

### "mt-email-field" has no property "value" anymore
Replace all occurrences of the "value" prop with "modelValue"

Before:
```html
<mt-email-field value="Hello World" />
```
After:
```html
<mt-email-field modelValue="Hello World" />
```

### "mt-email-field" v-model:value is deprecated
Replace all occurrences of the "v-model:value" directive with "v-model"

Before:
```html
<mt-email-field v-model:value="myValue" />
```
After:
```html
<mt-email-field v-model="myValue" />
```

### "mt-email-field" has no property "size" with value "medium" anymore
Replace all occurrences of the "size" prop with "default"

Before:
```html
<mt-email-field size="medium" />
```
After:
```html
<mt-email-field size="default" />
```

### "mt-email-field" has no property "isInvalid" anymore
Remove all occurrences of the "isInvalid" prop

Before:
```html
<mt-email-field isInvalid />
```
After:
```html
<mt-email-field />
```

### "mt-email-field" has no property "aiBadge" anymore
Remove all occurrences of the "aiBadge" prop

Before:
```html
<mt-email-field aiBadge />
```
After:
```html
<mt-email-field />
```

### "mt-email-field" has no event "update:value" anymore
Replace all occurrences of the "update:value" event with "update:modelValue"

Before:
```html
<mt-email-field @update:value="updateValue" />
```

After:
```html
<mt-email-field @update:modelValue="updateValue" />
```

### "mt-email-field" has no event "base-field-mounted" anymore
Remove all occurrences of the "base-field-mounted" event

Before:
```html
<mt-email-field @base-field-mounted="onFieldMounted" />
```
After:
```html
<mt-email-field />
```

### "mt-email-field" has no slot "label" anymore
Remove all occurrences of the "label" slot. The slot content should be moved to the "label" prop. Only string values are supported. Other slot content is not supported
anymore.

Before:
```html
<mt-email-field>
    <template #label>
        My Label
    </template>
</mt-email-field>
```
After:
```html
<mt-email-field label="My label">
</mt-email-field>
```
## Removal of "sw-url-field":
The old "sw-url-field" component will be removed in the next major version. Please use the new "mt-url-field" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-url-field" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-url-field" with "mt-url-field".

Following changes are necessary:

### "sw-url-field" is removed
Replace all component names from "sw-url-field" with "mt-url-field"

Before:
```html
<sw-url-field />
```
After:
```html
<mt-url-field />
```

### "mt-url-field" has no property "value" anymore
Replace all occurrences of the "value" prop with "modelValue"

Before:
```html
<sw-url-field value="Hello World" />
```
After:
```html
<mt-url-field modelValue="Hello World" />
```

### "mt-url-field" v-model:value is deprecated
Replace all occurrences of the "v-model:value" directive with "v-model"

Before:
```html
<sw-url-field v-model:value="myValue" />
```
After:
```html
<mt-url-field v-model="myValue" />
```

### "mt-url-field" has no event "update:value" anymore
Replace all occurrences of the "update:value" event with "update:modelValue"

Before:
```html
<sw-url-field @update:value="updateValue" />
```

After:
```html
<mt-url-field @update:modelValue="updateValue" />
```

### "mt-url-field" has no slot "label" anymore
Remove all occurrences of the "label" slot. The slot content should be moved to the "label" prop. Only string values are supported. Other slot content is not supported
anymore.

Before:
```html
<sw-url-field>
    <template #label>
        My Label
    </template>
</sw-url-field>
```
After:
```html
<mt-url-field label="My label">
</mt-url-field>
```

### "mt-url-field" has no slot "hint" anymore
Remove all occurrences of the "hint" slot. There is no replacement for this slot.

Before:
```html
<sw-url-field>
    <template #hint>
        My Hint
    </template>
</sw-url-field>
```

After:
```html
<mt-url-field />
```
## Removal of "sw-progress-bar":
The old "sw-progress-bar" component will be removed in the next major version. Please use the new "mt-progress-bar" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-progress-bar" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-progress-bar" with "mt-progress-bar".

Following changes are necessary:

### "sw-progress-bar" is removed
Replace all component names from "sw-progress-bar" with "mt-progress-bar"

Before:
```html
<sw-progress-bar />
```
After:
```html
<mt-progress-bar />
```

### "mt-progress-bar" has no property "value" anymore
Replace all occurrences of the "value" prop with "modelValue"

Before:
```html
<mt-progress-bar value="5" />
```
After:
```html
<mt-progress-bar modelValue="5" />
```

### "mt-progress-bar" v-model:value is deprecated
Replace all occurrences of the "v-model:value" directive with "v-model"

Before:
```html
<mt-progress-bar v-model:value="myValue" />
```
After:
```html
<mt-progress-bar v-model="myValue" />
```

### "mt-progress-bar" has no event "update:value" anymore
Replace all occurrences of the "update:value" event with "update:modelValue"

Before:
```html
<mt-progress-bar @update:value="updateValue" />
```

After:
```html
<mt-progress-bar @update:modelValue="updateValue" />
```g

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
