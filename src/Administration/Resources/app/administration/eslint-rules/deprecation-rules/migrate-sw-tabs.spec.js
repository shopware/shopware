/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./migrate-sw-tabs');

const MIGRATE = 'sw-tabs is deprecated. Use mt-tabs with the items prop instead.';
const POSITION = 'mt-tabs requires a position-identifier. Add a unique one, so extensions can add tabs.';

function manual(reason) {
    return 'sw-tabs is deprecated. Replace it with mt-tabs and pass the sw-tabs-item children as :items '
        + '({ name, label, hasError, disabled, badge, onClick }); add small, and @new-item-active now receives the item name. '
        + `Not converted automatically because of ${reason}.`;
}

const twigTodo = (message) => `{# TODO Codemod(6.8, mt-tabs): ${message} #}`;
const template = (...lines) => ['<template>', ...lines, '</template>'].join('\n');

const convertedRoutes = template(
    '    <mt-tabs',
    '        position-identifier="acme-tabs"',
    '        small',
    '        :default-item="$route.name"',
    '        :items="[',
    "            { name: 'general', label: $t('acme.tabs.general') },",
    "            { name: 'acme.detail.seo', label: $t('acme.tabs.seo'), hasError: seoError, onClick: () => $router.push({ name: 'acme.detail.seo' }) },",
    '        ]"',
    '        @new-item-active="onTab($event)"',
    '    />',
);

const convertedVue = template(
    '    <mt-tabs',
    '        position-identifier="acme"',
    '        vertical',
    '        :small="false"',
    '        :items="[',
    "            { name: 'a', label: $t('acme.a'), disabled: true, badge: warnings.a ? 'warning' : undefined },",
    "            ...(showB ? [{ name: 'b', label: 'B', badge: 'warning' }] : []),",
    '        ]"',
    '    />',
);

const convertedSlots = template(
    `    ${twigTodo(POSITION)}`,
    '    <mt-tabs',
    '        class="acme-tabs"',
    '        default-item="general"',
    '        small',
    '        :items="[',
    "            { name: 'general', label: $tc('acme.tabs.general') },",
    "            ...(!isCreate ? [{ name: 'log', label: 'Log' }] : []),",
    '        ]"',
    '    >',
    '        <template #content="{ active }">',
    '            {% block acme_tabs_content %}',
    '            <acme-general v-if="active === \'general\'" />',
    '            {% endblock %}',
    '        </template>',
    '    </mt-tabs>',
);

const GENERAL = '<sw-tabs-item name="general">General</sw-tabs-item>';
const tabs = (items, attributes = '') => `<sw-tabs position-identifier="acme"${attributes}>${items}</sw-tabs>`;

/**
 * sw-tabs the rule does not convert, as `[reason, ...elements]`: it only writes a codemod comment with the reason above each element.
 */
const UNCONVERTIBLE = [
    ['a Twig block or comment in sw-tabs', tabs(`{% block acme_tab_general %}${GENERAL}{% endblock %}`)],
    ['an HTML comment in sw-tabs', tabs(`<!-- the general tab -->${GENERAL}`)],
    [
        'the @new-item-active handler, which receives the item name instead of the item now',
        tabs(GENERAL, ' @new-item-active="setActiveTab"'),
        tabs(GENERAL, ' @new-item-active="activeTab = $event"'),
    ],
    ['v-slot on sw-tabs', tabs(GENERAL, ' v-slot="{ active }"')],
    ['a ref on sw-tabs', tabs(GENERAL, ' ref="tabs"')],
    ['use-meteor-component or items on sw-tabs', '<sw-tabs position-identifier="acme" use-meteor-component :items="tabs" />'],
    ['default-item on sw-tabs with route items', tabs('<sw-tabs-item :route="{ name: \'acme.general\' }">General</sw-tabs-item>', ' default-item="acme.general"')],
    ['the #header slot of sw-tabs', tabs('<template #header>Header</template>')],
    ['a dynamic slot name on sw-tabs', tabs('<template #[slotName]>Header</template>')],
    ['a directive on the default slot of sw-tabs', tabs(`<template v-if="isReady" #default>${GENERAL}</template>`)],
    ['content next to the #default slot of sw-tabs', tabs(`<template #default>${GENERAL}</template><sw-tabs-item name="log">Log</sw-tabs-item>`)],
    ['text next to sw-tabs-item', tabs(`Tabs: ${GENERAL}`)],
    ['a <div> element in sw-tabs', tabs('<div>General</div>')],
    ['v-for on sw-tabs-item', tabs('<sw-tabs-item v-for="tab in tabs" :key="tab" name="x">X</sw-tabs-item>')],
    ['v-else on sw-tabs-item', tabs(`<sw-tabs-item v-if="isNew" name="new">New</sw-tabs-item><sw-tabs-item v-else name="edit">Edit</sw-tabs-item>`)],
    ['a bound :name on sw-tabs-item', tabs('<sw-tabs-item :name="tab">General</sw-tabs-item>')],
    ['a bound :class on sw-tabs-item', tabs('<sw-tabs-item name="general" :class="tabClass">General</sw-tabs-item>')],
    ['a class on sw-tabs-item', tabs('<sw-tabs-item name="general" class="acme-tabs__general">General</sw-tabs-item>')],
    ['a tooltip on sw-tabs-item', tabs('<sw-tabs-item name="general" :error-tooltip="tooltip">General</sw-tabs-item>')],
    ['the active prop on sw-tabs-item', tabs('<sw-tabs-item name="general" active>General</sw-tabs-item>')],
    ['active-tab on sw-tabs-item', tabs('<sw-tabs-item name="general" :active-tab="currentTab">General</sw-tabs-item>')],
    ['the ref attribute on sw-tabs-item', tabs('<sw-tabs-item ref="general" name="general">General</sw-tabs-item>')],
    [
        'a label with markup in sw-tabs-item',
        tabs('<sw-tabs-item name="general"><mt-icon name="regular-cog" /> General</sw-tabs-item>'),
        tabs('<sw-tabs-item name="general">{{ $t(\'acme.general\') }} ({{ count }})</sw-tabs-item>'),
    ],
    ['a title that differs from the label of sw-tabs-item', tabs('<sw-tabs-item name="general" title="Basic settings">General</sw-tabs-item>')],
    ['a sw-tabs-item without a label', tabs('<sw-tabs-item name="general" />')],
    ['a sw-tabs-item without name or route', tabs('<sw-tabs-item>General</sw-tabs-item>')],
    ['a route path on sw-tabs-item', tabs('<sw-tabs-item route="/acme/general">General</sw-tabs-item>')],
    ['a route without a static route name on sw-tabs-item', tabs('<sw-tabs-item :route="generalRoute">General</sw-tabs-item>')],
    [
        'a name that differs from the route name of sw-tabs-item',
        tabs('<sw-tabs-item name="general" :route="{ name: \'acme.general\' }">General</sw-tabs-item>'),
    ],
];

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('migrate-sw-tabs', rule, {
    valid: [
        {
            name: 'a second run leaves the converted mt-tabs alone',
            filename: 'test.html.twig',
            code: convertedRoutes,
        },
        {
            name: 'ignores sw-tabs-item outside of sw-tabs',
            filename: 'test.html.twig',
            code: template(
                '    <sw-meteor-page>',
                '        <template #page-tabs>',
                '            <sw-tabs-item name="general">General</sw-tabs-item>',
                '        </template>',
                '    </sw-meteor-page>',
            ),
        },
        {
            name: 'ignores script files',
            code: 'const tabs = "<sw-tabs></sw-tabs>";',
            languageOptions: { parser: require('espree') },
        },
    ],
    invalid: [
        {
            name: 'converts items with names, routes, errors and the new-item-active payload',
            filename: 'test.html.twig',
            code: template(
                '    <sw-tabs position-identifier="acme-tabs" @new-item-active="onTab($event.name)">',
                "        <sw-tabs-item name=\"general\">{{ $t('acme.tabs.general') }}</sw-tabs-item>",
                "        <sw-tabs-item :route=\"{ name: 'acme.detail.seo' }\" :has-error=\"seoError\">{{ $t('acme.tabs.seo') }}</sw-tabs-item>",
                '    </sw-tabs>',
            ),
            output: convertedRoutes,
            errors: [{ message: MIGRATE }],
        },
        {
            name: 'converts the default slot, keeps the content slot and adds items conditionally',
            filename: 'test.html.twig',
            code: template(
                '    <sw-tabs class="acme-tabs" default-item="general">',
                '        <template #default="{ active }">',
                '            <sw-tabs-item',
                '                name="general"',
                "                :title=\"$tc('acme.tabs.general')\"",
                '                :active-tab="active"',
                '            >',
                "                {{ $tc('acme.tabs.general') }}",
                '            </sw-tabs-item>',
                '            <sw-tabs-item v-if="!isCreate" name="log" :active-tab="active">',
                '                Log',
                '            </sw-tabs-item>',
                '        </template>',
                '',
                '        <template #content="{ active }">',
                '            {% block acme_tabs_content %}',
                '            <acme-general v-if="active === \'general\'" />',
                '            {% endblock %}',
                '        </template>',
                '    </sw-tabs>',
            ),
            output: convertedSlots,
            errors: [{ message: POSITION }, { message: MIGRATE }],
        },
        {
            name: 'converts flags, double-quoted labels and the renamed props in .vue files',
            filename: 'test.vue',
            code: template(
                '    <sw-tabs position-identifier="acme" is-vertical align-right :small="false">',
                '        <template v-slot="slotProps">',
                '            <sw-tabs-item name="a" disabled :has-warning="warnings.a" :active-tab="slotProps.active">',
                '                {{ $t("acme.a") }}',
                '            </sw-tabs-item>',
                '            <sw-tabs-item name="b" has-warning v-show="showB" title="B" />',
                '        </template>',
                '    </sw-tabs>',
            ),
            output: convertedVue,
            errors: [{ message: MIGRATE }],
        },
        {
            name: 'converts sw-tabs without items',
            filename: 'test.html.twig',
            code: template('<sw-tabs position-identifier="acme"></sw-tabs>'),
            output: template('<mt-tabs\n    position-identifier="acme"\n    small\n    :items="[]"\n/>'),
            errors: [{ message: MIGRATE }],
        },
        {
            name: 'a second run only reports the marked position-identifier',
            filename: 'test.html.twig',
            code: convertedSlots,
            output: null,
            errors: [{ message: POSITION }],
        },
        {
            name: 'marks mt-tabs without position-identifier',
            filename: 'test.html.twig',
            code: template('    <mt-tabs :items="items" />'),
            output: template(`    ${twigTodo(POSITION)}`, '    <mt-tabs :items="items" />'),
            errors: [{ message: POSITION }],
        },
        {
            name: 'writes .vue comments in .vue files',
            filename: 'test.vue',
            code: template(
                '    <sw-tabs position-identifier="acme">',
                '        <sw-tabs-item name="a" @click="onA">A</sw-tabs-item>',
                '    </sw-tabs>',
            ),
            output: template(
                `    <!-- TODO Codemod(6.8, mt-tabs): ${manual('@click on sw-tabs-item')} -->`,
                '    <sw-tabs position-identifier="acme">',
                '        <sw-tabs-item name="a" @click="onA">A</sw-tabs-item>',
                '    </sw-tabs>',
            ),
            errors: [{ message: manual('@click on sw-tabs-item') }],
        },
        ...UNCONVERTIBLE.map(([reason, ...elements]) => ({
            name: `marks sw-tabs it does not convert because of ${reason}`,
            filename: 'test.html.twig',
            code: template(...elements.map((element) => `    ${element}`)),
            output: template(...elements.flatMap((element) => [`    ${twigTodo(manual(reason))}`, `    ${element}`])),
            errors: elements.map(() => ({ message: manual(reason) })),
        })),
    ],
});
