---
title: Make more use of Bootstrap tooling and remove !important from Bootstrap CSS utils
date: 2023-10-19
area: storefront
tags: [storefront, bootstrap, css]
---

## Context

At the moment the Storefront is implementing a lot of custom SCSS inside `app/storefront/src/scss`.
Some custom SCSS is not necessary, and it already exists within Bootstrap as a utility.
For example default spacing with a custom selector:

```scss
.register-login-collapse-toogle {
    margin-bottom: $spacer;
}
```

This can be replaced with a Bootstrap [spacing utility](https://getbootstrap.com/docs/5.2/utilities/spacing/) in the HTML and the SCSS can be completely removed:

```diff
- <div class="register-login-collapse-toogle"><div>
+ <div class="register-login-collapse-toogle mb-3"><div>
```

A class like `register-login-collapse-toogle` should stay in the HTML in case a developer wants to style this specific element.
But there is no need to introduce a new CSS rule using a custom selector to apply a default spacing.

If you implement new UI using mostly utility classes, please consider to still add CSS classes that offer the possibility for themes to add individual styling. For example:

```html
<!-- Classes "shipping-modal-actions", "shipping-abort" and "shipping-submit" are added for better semantics and CSS extensibility, but ship no default CSS. -->
<div class="border p-3 mb-3 shipping-modal-actions">
    <button class="btn btn-light shipping-abort">Abort</button>
    <button class="btn btn-primary shipping-submit">Submit</button>
</div>
```

This principle cannot be applied everywhere. 
For more complex layouts it can still be valid to use custom SCSS because it is not possible to build with default components and utilities, or it would produce a messy template with too many generic utility classes. 
However, for simpler stuff like "add a border here, add some spacing there" it's not necessary to implement additional custom styling.

## Decision

We want to make more use of Bootstrap utilities and get rid of custom SCSS that is not needed.

In order to do so we want to remove unneeded SCSS and add a utility class to the HTML instead (e.g. `mb-3`).
However, this can break styling overrides of themes/apps because most Bootstrap utility classes apply `!important` by default.

Let's stick to the example of `.register-login-collapse-toogle`.
* The core Storefront adds a bottom margin of `$spacer` which equals `1rem`.
* Then `CustomTheme` overrides this selector with a margin bottom of `80px`:

```diff
/* CustomTheme */
.register-login-collapse-toogle {
+    margin-bottom: 80px;
}

/* Storefront */
.register-login-collapse-toogle {
-    margin-bottom: 1rem;
}
```

If the core Storefront would migrate to the utility class it would suddenly overrule the styling of `CustomTheme` because of the `!important` property:

```diff
/* Utility class from HTML overrules CustomTheme */
.mb-3 {
+    margin-bottom: 1rem !important;
}

/* CustomTheme */
.register-login-collapse-toogle {
-    margin-bottom: 80px;
}

/* Storefront */
.register-login-collapse-toogle {
-    margin-bottom: 1rem;
}
```

The theme developer would have no other choice other than using `!important` as well, or modifying the Twig template.

Because of this, we have decided to remove the `!important` from Bootstrap utility classes by changing the [Importance](https://getbootstrap.com/docs/5.2/utilities/api/#importance) variable `$enable-important-utilities` to `false`.
By doing this, we can use more utilities while at the same time allowing themes to override the same CSS property without using `!important` or editing the template.

Since it is currently expected that Bootstrap utilities add `!important` and overrule almost everything, we do not want to change this right away but from `v6.6.0` onwards.

### Bootstrap components first

We want to commit more to the standards of our framework and make better use of the default components, especially their configuration.

We should follow this principle:

1. Build something with Bootstrap default components and utilities first.
2. If the appearance does not fully suite our needs, use [component configuration and variables](https://getbootstrap.com/docs/5.2/components/buttons/#variables) for customization whenever possible.
3. Only when there is not enough config available, or in case of complex layouts that cannot be built using default components and utilities, we should rely on custom styling.

## Consequences

* In `v6.6.0` Bootstrap CSS utilities like `mb-3` will no longer apply the `!important` property.
* Unneeded custom styling will be removed from the SCSS and migrated to Bootstrap utilities.
