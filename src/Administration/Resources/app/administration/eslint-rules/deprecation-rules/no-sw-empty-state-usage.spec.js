/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-sw-empty-state-usage');

const ruleTester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2021,
    },
});

const TODO = '<!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly -->';
const MIGRATE_MESSAGE = '"sw-empty-state" is deprecated and will be removed in 6.9. Please use "mt-empty-state" instead.';

function manualMessage(reason) {
    return `"sw-empty-state" is deprecated and will be removed in 6.9. It can't be converted to "mt-empty-state" automatically, because ${reason}. Please migrate it manually.`;
}

ruleTester.run('no-sw-empty-state-usage', rule, {
    valid: [
        {
            name: 'allows empty files',
            filename: 'test.html.twig',
            code: '',
        },
        {
            name: 'allows mt-empty-state',
            filename: 'test.html.twig',
            code: `
<template>
    <mt-empty-state headline="Title" description="Description" icon="regular-products" />
</template>`,
        },
    ],
    invalid: [
        {
            name: 'renames title and subline and adds the module icon fallback',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state :title="$t('foo.title')" :subline="$t('foo.subline')" />
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state :headline="$t('foo.title')" :description="$t('foo.subline')" :icon="$route.meta.$module.icon" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'adds the module description fallback without subline',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title" icon="regular-products" />
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state headline="Title" icon="regular-products" :description="$t($route.meta.$module.description)" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'keeps multi line attributes, removes layout props and renames the actions slot',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state
        v-if="!items.length"
        :title="$t('foo.title')"
        subline="Static subline"
        icon="regular-products"
        :absolute="false"
        empty-module
    >
        <template #actions>
            <mt-button @click="onCreate">Create</mt-button>
        </template>
    </sw-empty-state>
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. Removed props without replacement: absolute, emptyModule -->
    <mt-empty-state
        v-if="!items.length"
        :headline="$t('foo.title')"
        description="Static subline"
        icon="regular-products"
        centered
    >
        <template #button>
            <mt-button @click="onCreate">Create</mt-button>
        </template>
    </mt-empty-state>
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'renames the actions slot in v-slot syntax and keeps surrounding Twig blocks',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title" icon="regular-products" subline="Subline">
        {% block my_plugin_empty_state_actions %}
        <template v-slot:actions>
            <mt-button />
        </template>
        {% endblock %}
    </sw-empty-state>
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state headline="Title" icon="regular-products" description="Subline" centered>
        {% block my_plugin_empty_state_actions %}
        <template v-slot:button>
            <mt-button />
        </template>
        {% endblock %}
    </mt-empty-state>
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'renames every conditional actions slot template',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title" icon="regular-products" subline="Subline">
        <template v-if="canCreate" #actions>
            <mt-button>Create</mt-button>
        </template>
        <template v-else #actions>
            <mt-button>Request</mt-button>
        </template>
    </sw-empty-state>
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state headline="Title" icon="regular-products" description="Subline" centered>
        <template v-if="canCreate" #button>
            <mt-button>Create</mt-button>
        </template>
        <template v-else #button>
            <mt-button>Request</mt-button>
        </template>
    </mt-empty-state>
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'converts an icon slot with a single icon and unwraps the default slot text',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state :title="title" icon="regular-products">
        <template #icon>
            <mt-icon name="regular-rocket" size="40px" />
        </template>
        <p>{{ $t('foo.description') }}</p>
    </sw-empty-state>
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state :headline="title" :description="$t('foo.description')" icon="regular-rocket" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'converts a bound icon name and an empty default slot',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state :title="title" subline="Ignored">
        <template #icon><sw-icon :name="iconName" /></template>
        <template #default></template>
    </sw-empty-state>
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state :headline="title" :icon="iconName" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'moves default slot text into the description and keeps the remaining slots',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state :title="title" icon="regular-products">
        {{ $t("foo.description") }}
        <template #actions>
            <mt-button />
        </template>
    </sw-empty-state>
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state :headline="title" icon="regular-products" :description='$t("foo.description")' centered>
        <template #button>
            <mt-button />
        </template>
    </mt-empty-state>
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'combines mixed default slot content and a dynamic showDescription',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state :title="title" icon="regular-products" :show-description="hasDescription">
        Found {{ count }} \`items\`
    </sw-empty-state>
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state :headline="title" icon="regular-products" :description="hasDescription ? \`Found \${count} \\\`items\\\`\` : ''" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'wraps complex showDescription conditions',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title" icon="regular-products" subline="Subline" :show-description="a || b" />
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state headline="Title" icon="regular-products" :description="(a || b) ? 'Subline' : ''" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'notes the lost module fallbacks of bound subline and icon values',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state :title="title" :subline="emptyHint" :icon="orderIcon" />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. "description" no longer falls back to the module description if it is null or undefined. "icon" no longer falls back to the module icon if it is null or undefined -->
    <mt-empty-state :headline="title" :description="emptyHint" :icon="orderIcon" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'does not note module fallbacks for bound values which always resolve to a value',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title" :subline="filtered ? $tc('foo.filtered') : $t('foo.empty')" :icon="'regular-' + type" />
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state headline="Title" :description="filtered ? $tc('foo.filtered') : $t('foo.empty')" :icon="'regular-' + type" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'does not note module fallbacks for a hidden description or the module icon itself',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title" :subline="emptyHint" :show-description="false" :icon="$route.meta.$module.icon" />
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state headline="Title" :icon="$route.meta.$module.icon" description="" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'passes an empty description for a disabled showDescription',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state :title="title" icon="regular-products" :show-description="false" color="#fff"></sw-empty-state>
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. Removed props without replacement: color -->
    <mt-empty-state :headline="title" icon="regular-products" description="" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'keeps an existing centered attribute',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title" icon="regular-products" subline="Subline" centered />
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state headline="Title" icon="regular-products" description="Subline" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'notes that a static HTML description is rendered as text',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Say &quot;hi&quot;" icon="regular-products" subline="<b>bold</b>" auto-height />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. Removed props without replacement: autoHeight. The description is rendered as plain text, HTML is no longer supported -->
    <mt-empty-state headline="Say &quot;hi&quot;" icon="regular-products" description="<b>bold</b>" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'reports custom icon slot content without a fix',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title">
        <template #icon>
            <img :src="assetFilter('my-plugin/empty-state.svg')" alt="">
        </template>
    </sw-empty-state>
</template>`,
            output: null,
            errors: [{
                message: manualMessage('the "icon" slot renders custom content, but "mt-empty-state" only supports the "icon" prop'),
            }],
        },
        {
            name: 'reports conditional icon slot templates without a fix',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title">
        <template v-if="isActive" #icon><mt-icon name="regular-checkmark" /></template>
        <template v-else #icon><mt-icon name="regular-times" /></template>
    </sw-empty-state>
</template>`,
            output: null,
            errors: [{
                message: manualMessage('the "icon" slot template has directives like v-if, which cannot be mapped to the "icon" prop'),
            }],
        },
        {
            name: 'reports a conditional default slot template without a fix',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title">
        <template v-if="hasText" #default>Text</template>
    </sw-empty-state>
</template>`,
            output: null,
            errors: [{
                message: manualMessage('the default slot template has directives like v-if, which cannot be mapped to the "description" prop'),
            }],
        },
        {
            name: 'reports markup in the default slot without a fix',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title">
        <template #default>
            <div class="description">Rich <b>text</b></div>
        </template>
    </sw-empty-state>
</template>`,
            output: null,
            errors: [{
                message: manualMessage('the default slot renders markup, but "mt-empty-state" only supports a plain text "description"'),
            }],
        },
        {
            name: 'reports Twig blocks inside the default slot without a fix',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title">
        <p>{% block my_plugin_description %}Text{% endblock %}</p>
    </sw-empty-state>
</template>`,
            output: null,
            errors: [{
                message: manualMessage('the default slot renders markup, but "mt-empty-state" only supports a plain text "description"'),
            }],
        },
        {
            name: 'reports Twig blocks inside the default slot template without a fix',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title">
        <template #default>
            {% block my_plugin_description %}{{ description }}{% endblock %}
        </template>
    </sw-empty-state>
</template>`,
            output: null,
            errors: [{
                message: manualMessage('the default slot renders markup, but "mt-empty-state" only supports a plain text "description"'),
            }],
        },
        {
            name: 'keeps empty Twig blocks inside the element',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title" icon="regular-products" subline="Subline">
        {% block my_plugin_empty_state_content %}{% endblock %}
    </sw-empty-state>
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state headline="Title" icon="regular-products" description="Subline" centered>
        {% block my_plugin_empty_state_content %}{% endblock %}
    </mt-empty-state>
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'reports dynamic bindings without a fix',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title" v-bind="emptyStateProps" />
</template>`,
            output: null,
            errors: [{
                message: manualMessage('dynamic bindings (v-bind="..." or :[...]) cannot be mapped to the new props'),
            }],
        },
        {
            name: 'reports dynamic slot names without a fix',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title">
        <template #[slotName]>Content</template>
    </sw-empty-state>
</template>`,
            output: null,
            errors: [{
                message: manualMessage('dynamic slot names cannot be mapped to the new slots'),
            }],
        },
        {
            name: 'reports descriptions mixing both quote types without a fix',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-empty-state title="Title" icon="regular-products">
        {{ $t("foo", { name: 'bar' }) }}
    </sw-empty-state>
</template>`,
            output: null,
            errors: [{
                message: manualMessage('the description expression mixes single and double quotes'),
            }],
        },
        {
            name: 'parses mustaches in Vue files',
            filename: 'test.vue',
            code: `
<template>
    <sw-empty-state :title="title" icon="regular-products">
        Found {{ count }} items
    </sw-empty-state>
</template>`,
            output: `
<template>
    ${TODO}
    <mt-empty-state :headline="title" icon="regular-products" :description="\`Found \${count} items\`" centered />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
    ],
});
