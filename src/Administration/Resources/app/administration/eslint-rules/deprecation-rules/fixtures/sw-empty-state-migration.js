/**
 * @sw-package framework
 */

// The sw-empty-state migration end to end: the component conversion and its follow-up steps, run
// like deprecation:migrate --fix runs them. Each case gives the result at the partial and safe levels.

module.exports = [
    {
        name: 'renames title and subline and adds the module icon fallback',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state :title="$t('foo.title')" :subline="$t('foo.subline')" />
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "description" prop replaces the "default" slot and the "subline" prop. "description" is rendered as plain text: check that the bound value holds no HTML. -->
    <mt-empty-state :headline="$t('foo.title')" :description="$t('foo.subline')" :icon="$route.meta.$module.icon" centered />
</template>`,
        safe: null,
    },
    {
        name: 'adds the module description fallback without subline',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title" icon="regular-products" />
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <mt-empty-state headline="Title" icon="regular-products" :description="$t($route.meta.$module.description)" centered />
</template>`,
        safe: null,
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
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly -->
    <!-- TODO Codemod: [mt-empty-state] mt-empty-state has no "empty-module" prop: check the look without it. -->
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
        safe: null,
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
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <mt-empty-state headline="Title" icon="regular-products" description="Subline" centered>
        {% block my_plugin_empty_state_actions %}
        <template v-slot:button>
            <mt-button />
        </template>
        {% endblock %}
    </mt-empty-state>
</template>`,
        safe: null,
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
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <mt-empty-state headline="Title" icon="regular-products" description="Subline" centered>
        <template v-if="canCreate" #button>
            <mt-button>Create</mt-button>
        </template>
        <template v-else #button>
            <mt-button>Request</mt-button>
        </template>
    </mt-empty-state>
</template>`,
        safe: null,
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
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "icon" prop replaces the <mt-icon> in the "icon" slot and the "icon" prop. The slot element's "size" cannot move into "icon" and is dropped. -->
    <mt-empty-state :headline="title" icon="regular-rocket" centered :description="$t('foo.description')">
    </mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'converts a bound icon name and keeps the subline for an empty default slot, which renders its fallback',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state :title="title" subline="Shown">
        <template #icon><sw-icon :name="iconName" /></template>
        <template #default></template>
    </sw-empty-state>
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "icon" prop replaces the <mt-icon> in the "icon" slot and the "icon" prop. "icon" no longer falls back to "$route.meta.$module.icon" when it is null or undefined. -->
    <mt-empty-state :headline="title" description="Shown" centered :icon="iconName">
    </mt-empty-state>
</template>`,
        safe: null,
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
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <mt-empty-state :headline="title" icon="regular-products" centered :description='$t("foo.description")'>
        <template #button>
            <mt-button />
        </template>
    </mt-empty-state>
</template>`,
        safe: null,
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
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <mt-empty-state :headline="title" icon="regular-products" :description="hasDescription ? \`Found \${count} \\\`items\\\`\` : ''" centered>
    </mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'wraps complex showDescription conditions',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title" icon="regular-products" subline="Subline" :show-description="a || b" />
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <mt-empty-state headline="Title" icon="regular-products" :description="(a || b) ? 'Subline' : ''" centered />
</template>`,
        safe: null,
    },
    {
        name: 'notes the lost module fallbacks of bound subline and icon values',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state :title="title" :subline="emptyHint" :icon="orderIcon" />
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "icon" prop replaces the <mt-icon> in the "icon" slot and the "icon" prop. "icon" no longer falls back to "$route.meta.$module.icon" when it is null or undefined. -->
    <!-- TODO Codemod: [mt-empty-state] The "description" prop replaces the "default" slot and the "subline" prop. "description" no longer falls back to "$t($route.meta.$module.description)" when it is null or undefined. "description" is rendered as plain text: check that the bound value holds no HTML. -->
    <mt-empty-state :headline="title" :description="emptyHint" :icon="orderIcon" centered />
</template>`,
        safe: null,
    },
    {
        name: 'does not note module fallbacks for bound values which always resolve to a value',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title" :subline="filtered ? $tc('foo.filtered') : $t('foo.empty')" :icon="'regular-' + type" />
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "description" prop replaces the "default" slot and the "subline" prop. "description" is rendered as plain text: check that the bound value holds no HTML. -->
    <mt-empty-state headline="Title" :description="filtered ? $tc('foo.filtered') : $t('foo.empty')" :icon="'regular-' + type" centered />
</template>`,
        safe: null,
    },
    {
        name: 'does not note module fallbacks for a hidden description or the module icon itself',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title" :subline="emptyHint" :show-description="false" :icon="$route.meta.$module.icon" />
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <mt-empty-state headline="Title" :icon="$route.meta.$module.icon" description="" centered />
</template>`,
        safe: null,
    },
    {
        name: 'passes an empty description for a disabled showDescription',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state :title="title" icon="regular-products" :show-description="false" color="#fff"></sw-empty-state>
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] mt-empty-state has no "color" prop: check the look without it. -->
    <mt-empty-state :headline="title" icon="regular-products" description="" centered></mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'keeps an existing centered attribute',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title" icon="regular-products" subline="Subline" centered />
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <mt-empty-state headline="Title" icon="regular-products" description="Subline" centered />
</template>`,
        safe: null,
    },
    {
        name: 'notes that a static HTML description is rendered as text',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Say &quot;hi&quot;" icon="regular-products" subline="<b>bold</b>" auto-height />
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "description" prop replaces the "default" slot and the "subline" prop. "description" is rendered as plain text, so HTML in it shows as text. -->
    <!-- TODO Codemod: [mt-empty-state] mt-empty-state has no "auto-height" prop: check the look without it. -->
    <mt-empty-state headline="Say &quot;hi&quot;" icon="regular-products" description="<b>bold</b>" centered />
</template>`,
        safe: null,
    },
    {
        name: 'leaves an image in the icon slot for a manual move',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title">
        <template #icon>
            <img :src="assetFilter('my-plugin/empty-state.svg')" alt="">
        </template>
    </sw-empty-state>
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "icon" prop replaces the <mt-icon> in the "icon" slot and the "icon" prop. The "icon" slot renders content that the prop cannot hold. Pass it as the "icon" prop manually. -->
    <mt-empty-state headline="Title" :description="$t($route.meta.$module.description)" centered>
        <template #icon>
            <img :src="assetFilter('my-plugin/empty-state.svg')" alt="">
        </template>
    </mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'leaves conditional icon slots for a manual move',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title">
        <template v-if="isActive" #icon><mt-icon name="regular-checkmark" /></template>
        <template v-else #icon><mt-icon name="regular-times" /></template>
    </sw-empty-state>
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "icon" prop replaces the <mt-icon> in the "icon" slot and the "icon" prop. The "icon" slot is rendered conditionally. Pass it as the "icon" prop manually. -->
    <mt-empty-state headline="Title" :description="$t($route.meta.$module.description)" centered>
        <template v-if="isActive" #icon><mt-icon name="regular-checkmark" /></template>
        <template v-else #icon><mt-icon name="regular-times" /></template>
    </mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'leaves a conditional default slot for a manual move',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title">
        <template v-if="hasText" #default>Text</template>
    </sw-empty-state>
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "description" prop replaces the "default" slot and the "subline" prop. The "default" slot is rendered conditionally. Pass it as the "description" prop manually. -->
    <mt-empty-state headline="Title" :icon="$route.meta.$module.icon" centered>
        <template v-if="hasText" #default>Text</template>
    </mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'leaves markup in the default slot for a manual move',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title">
        <template #default>
            <div class="description">Rich <b>text</b></div>
        </template>
    </sw-empty-state>
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "description" prop replaces the "default" slot and the "subline" prop. The "default" slot renders content that the prop cannot hold. Pass it as the "description" prop manually. -->
    <mt-empty-state headline="Title" :icon="$route.meta.$module.icon" centered>
        <template #default>
            <div class="description">Rich <b>text</b></div>
        </template>
    </mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'leaves a Twig block in the default slot content for a manual move',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title">
        <p>{% block my_plugin_description %}Text{% endblock %}</p>
    </sw-empty-state>
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "description" prop replaces the "default" slot and the "subline" prop. The "default" slot contains Twig, which a prop cannot hold. Pass it as the "description" prop manually. -->
    <mt-empty-state headline="Title" :icon="$route.meta.$module.icon" centered>
        <p>{% block my_plugin_description %}Text{% endblock %}</p>
    </mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'leaves a Twig block in the default slot template for a manual move',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title">
        <template #default>
            {% block my_plugin_description %}{{ description }}{% endblock %}
        </template>
    </sw-empty-state>
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "description" prop replaces the "default" slot and the "subline" prop. The "default" slot contains Twig, which a prop cannot hold. Pass it as the "description" prop manually. -->
    <mt-empty-state headline="Title" :icon="$route.meta.$module.icon" centered>
        <template #default>
            {% block my_plugin_description %}{{ description }}{% endblock %}
        </template>
    </mt-empty-state>
</template>`,
        safe: null,
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
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <mt-empty-state headline="Title" icon="regular-products" description="Subline" centered>
        {% block my_plugin_empty_state_content %}{% endblock %}
    </mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'leaves the props of an object v-bind for a manual review',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title" v-bind="emptyStateProps" />
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. Dynamic v-bind props may still contain deprecated API usage and need manual review. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "title" API is deprecated. Use "headline" instead. Object v-bind can hide the replacement prop. Review the bound object and rename "title" to "headline" manually if needed. -->
    <mt-empty-state title="Title" centered v-bind="emptyStateProps" :description="$t($route.meta.$module.description)" :icon="$route.meta.$module.icon" />
</template>`,
        safe: null,
    },
    {
        name: 'leaves a dynamic slot name for a manual move',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title">
        <template #[slotName]>Content</template>
    </sw-empty-state>
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "description" prop replaces the "default" slot and the "subline" prop. A slot with a dynamic name can fill one of its slots. Pass it as the "description" prop manually. -->
    <!-- TODO Codemod: [mt-empty-state] The "icon" prop replaces the <mt-icon> in the "icon" slot and the "icon" prop. A slot with a dynamic name can fill one of its slots. Pass it as the "icon" prop manually. -->
    <mt-empty-state headline="Title" centered>
        <template #[slotName]>Content</template>
    </mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'leaves a translation with mixed quotes for a manual move',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title" icon="regular-products">
        {{ $t("foo", { name: 'bar' }) }}
    </sw-empty-state>
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <!-- TODO Codemod: [mt-empty-state] The "description" prop replaces the "default" slot and the "subline" prop. The value mixes single and double quotes. Pass it as the "description" prop manually. -->
    <mt-empty-state headline="Title" icon="regular-products" centered>
        {{ $t("foo", { name: 'bar' }) }}
    </mt-empty-state>
</template>`,
        safe: null,
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
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <mt-empty-state :headline="title" icon="regular-products" centered :description="\`Found \${count} items\`">
    </mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'converts an empty icon slot to the module icon, which the slot rendered as fallback',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="T" subline="S"><template #icon></template></sw-empty-state>
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing -->
    <mt-empty-state headline="T" description="S" centered :icon="$route.meta.$module.icon"></mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'notes the layout only for an absolute positioning that can be on',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="T" subline="S" icon="a" :absolute="false" />
    <sw-empty-state title="T" subline="S" icon="a" :absolute="isOverlay" />
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly -->
    <mt-empty-state headline="T" description="S" icon="a" centered />
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly -->
    <!-- TODO Codemod: [mt-empty-state] The "absolute" prop is removed. sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing. -->
    <mt-empty-state headline="T" description="S" icon="a" centered />
</template>`,
        safe: `
<template>
    <mt-empty-state headline="T" description="S" icon="a" centered />
    <sw-empty-state title="T" subline="S" icon="a" :absolute="isOverlay" />
</template>`,
    },
    {
        name: 'keeps an icon with its own size from a safe conversion',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title" :absolute="false" subline="Sub">
        <template #icon><mt-icon name="regular-rocket" size="40px" /></template>
    </sw-empty-state>
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly -->
    <!-- TODO Codemod: [mt-empty-state] The "icon" prop replaces the <mt-icon> in the "icon" slot and the "icon" prop. The slot element's "size" cannot move into "icon" and is dropped. -->
    <mt-empty-state headline="Title" description="Sub" icon="regular-rocket" centered>
    </mt-empty-state>
</template>`,
        safe: null,
    },
    {
        name: 'keeps a bound subline, which sw-empty-state rendered as HTML, from a safe conversion',
        filename: 'test.html.twig',
        code: `
<template>
    <sw-empty-state title="Title" icon="regular-products" :absolute="false" :subline="$t('my.hint')" />
</template>`,
        partial: `
<template>
    <!-- TODO Codemod: Converted from sw-empty-state - please check if everything works correctly -->
    <!-- TODO Codemod: [mt-empty-state] The "description" prop replaces the "default" slot and the "subline" prop. "description" is rendered as plain text: check that the bound value holds no HTML. -->
    <mt-empty-state headline="Title" icon="regular-products" :description="$t('my.hint')" centered />
</template>`,
        safe: null,
    },
];
