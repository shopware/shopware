# 2021-05-28 - Vue Administration App has ESLint support

## Context

We want to support ESLint of the administration vue app to keep and increase code quality.

By adding ESLint to the administration app, every developer will get instant feedback and best practices on his code by writing.

## Decision

For the `*.js` linting, we want to get pretty close to a standard vue2 app linting. In the `*.js` linting chapter, we explain where and why we choose to leave the common way.

For the `*.html.twig` linting, we need to create a custom solution, which can handle or circumstance the twig syntax in our templates. We decided to convert all twig syntax to HTML comments within the linting process. This way, the linter ignores the twig parts and can handle the twig files like typical vue templates. The most significant tradeoff with this solution is that the linter cannot take the twig blocks into account on computing indentation levels.

### `*.js` linting

For the `*.js` files we try to follow a standard vue cli linting way, with this adjustments:

* [`'vue/require-prop-types': 'error'`](https://eslint.vuejs.org/rules/require-prop-types.html) - always use proper types definitions for `props`
* [`'vue/require-default-prop': 'error'`](https://eslint.vuejs.org/rules/require-default-prop.html) - always provide a default value for optional `props`
* [`'vue/no-mutating-props': ['off']`](https://eslint.vuejs.org/rules/no-mutating-props.html) - this is a tradeoff to allow mutating properties because it is already heavily used
* [`'vue/component-definition-name-casing': ['error', 'kebab-case']`](https://eslint.vuejs.org/rules/component-definition-name-casing.html) - write component names in kebab-casing

### `*.spec.js` linting

During writing unit test files, we do not want to get a `max-len` warning.
A `max-len` rule may lead to hard understandable output in test names only to suit the `max-len` rules.
In a test itself, you sometimes have `selector` phrases or something else where you exceed the `max-len` rule without a chance to solve it.

### `*.html.twig` linting

Besides the _twig-to-html-comment_ tradeoff, these exceptions are also made:

* `'vue/component-name-in-template-casing': ['error', 'kebab-case']` - write vue component names in kebab-case in templates
* `'vue/no-multiple-template-root': 'off',` - due to external template files and component inheritance
* `'vue/attribute-hyphenation': 'error'` - write `hello-word=""` attributes instead of `helloWorld=""`
* `'vue/no-parsing-error': ['error', {'nested-comment': false}]` - ignore nested html comments, which may be a result of the twig-to-html-comment workflow
* `'vue/valid-template-root': 'off'` - @see `vue/no-multiple-template-root`
* `'vue/valid-v-slot': ['error', { allowModifiers: true }]` - allow `.`s in template slot names 
* `'vue/no-unused-vars': 'off'` - the twig parser cannot understand if a scoped slot value is used or not used properly
* `'vue/no-template-shadow': 'off'` - for providing scoped values into another template scope
* `'vue/no-lone-template': 'off'` - in some composition cases lone template tags are used
* `'vue/no-v-html': 'off'` - for i18n and other reasons v-html is often used

### twig block indentation

To accomplish the twig syntax being able to be linted, we needed to create a custom [`eslint-twig-vue-plugin`](../src/Administration/Resources/app/administration/twigVuePlugin/lib/processors/twig-vue-processor.js) and to accept the following changes in template writing:

_before_
``` html
    …
    <div>
        {% block block_name %}
            <div>
                …
    …
```

_now_
``` html
    …
    <div>
        {% block block_name %}
        <div>
        …
```

To be able to lint the twig templates, we replace the twig syntax with HTML comments during the lint process, and thus every `twig` syntax is treated as an HTML comment and not recognised for indentation.

### self-closing components

_before_
``` html
…
    <sw-language-switcher></sw-language-switcher>
…
```

_now_
``` html
…
    <sw-language-switcher />
…
```

### attribute alignment

As soon as more than 1 attribute exists, every attribute gets its own line:

_before_
``` html
    …
    <div v-for="strategy in strategies" class="sw-app-app-url-changed-modal__content-choices">
    …
    <sw-icon small color="#189eff" name="default-basic-shape-circle-filled"></sw-icon>
    …
```

_now_
``` html
    …
    <div
        v-for="strategy in strategies"
        class="sw-app-app-url-changed-modal__content-choices"
    >
    …
    <sw-icon
        small
        color="#189eff"
        name="default-basic-shape-circle-filled"
    />
    …
```

## Linting Pitfalls

### invalid-x-end-tag

If you stumble upon a _very_ red marked file from your linter, please check first that your twig syntax follows this pattern:
``` twig
  {% block block_name %} ✔ <!-- whitespace after and before twig syntax `{% ` and ` %}`. -->
  {% block block_name%} ✘ <!-- missing whitespace after or before twig syntax `{%` or `%}` -->
```

### disabling eslint rules in templates

It is possible to disable a specific linting rule in the template by using this syntax:
``` html
    <!-- eslint-disable vue/eslint-rule-to-be-disabled -->
    <div>
    …
```
Please follow the _know the rules, break the rules_ approach and not the _dont bug me linter_ approach.

## ESLint IDE setup

The `*.js` linting should run out of the box with PHPStorm or VSCode. For `*.html.twig` linting have a look at the next chapter.

ESLint is part of the CI pipeline, so a running ESLint environment is mandatory.

### Twig Linting Setup

#### VSCode

Should work out of the box @see [.vscode/settings.json](../.vscode/settings.json).

#### PHPStorm

Add `html,twig` to `eslint.additional.file.extensions` list in Registry (Help > Find Action..., type registry... to locate it) and re-start the IDE.
