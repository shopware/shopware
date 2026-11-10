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

/**
 * Builds a test that expects a TODO above `<sw-tabs>` and no other change.
 */
function manualCase(name, reason, ...lines) {
    const code = template(...lines);
    const [first, ...rest] = lines;

    return {
        name,
        filename: 'test.html.twig',
        code,
        output: template(`    ${twigTodo(manual(reason))}`, first, ...rest),
        errors: [{ message: manual(reason) }],
    };
}

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('migrate-sw-tabs', rule, {
    valid: [
        {
            name: 'a second run leaves the converted mt-tabs alone',
            filename: 'test.html.twig',
            code: convertedRoutes,
        },
        {
            name: 'a second run leaves the converted .vue template alone',
            filename: 'test.vue',
            code: convertedVue,
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
                '                class="acme-tabs__general"',
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
            name: 'reports a marked mt-tabs without a fix',
            filename: 'test.html.twig',
            code: template(`    ${twigTodo(POSITION)}`, '    <mt-tabs :items="items" />'),
            output: null,
            errors: [{ message: POSITION }],
        },
        {
            name: 'reports a marked sw-tabs without a fix',
            filename: 'test.html.twig',
            code: template(
                `    ${twigTodo(manual('v-for on sw-tabs-item'))}`,
                '    <sw-tabs position-identifier="acme">',
                '        <sw-tabs-item v-for="tab in tabs" :key="tab" name="x">X</sw-tabs-item>',
                '    </sw-tabs>',
            ),
            output: null,
            errors: [{ message: manual('v-for on sw-tabs-item') }],
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
        manualCase(
            'does not convert Twig blocks around items',
            'a Twig block or comment in sw-tabs',
            '    <sw-tabs position-identifier="acme">',
            '        {% block acme_tab_general %}',
            '        <sw-tabs-item name="general">General</sw-tabs-item>',
            '        {% endblock %}',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert HTML comments between items',
            'an HTML comment in sw-tabs',
            '    <sw-tabs position-identifier="acme">',
            '        <!-- the general tab -->',
            '        <sw-tabs-item name="general">General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert handlers that receive the item',
            'the @new-item-active handler, which receives the item name instead of the item now',
            '    <sw-tabs position-identifier="acme" @new-item-active="setActiveTab">',
            '        <sw-tabs-item name="general">General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert handlers that use the item itself',
            'the @new-item-active handler, which receives the item name instead of the item now',
            '    <sw-tabs position-identifier="acme" @new-item-active="activeTab = $event">',
            '        <sw-tabs-item name="general">General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert bound names',
            'a bound :name on sw-tabs-item',
            '    <sw-tabs position-identifier="acme">',
            '        <sw-tabs-item :name="tab">General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert tooltips',
            'a tooltip on sw-tabs-item',
            '    <sw-tabs position-identifier="acme">',
            '        <sw-tabs-item name="general" :error-tooltip="tooltip">General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert the active prop',
            'the active prop on sw-tabs-item',
            '    <sw-tabs position-identifier="acme">',
            '        <sw-tabs-item name="general" active>General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert active-tab outside of the default slot',
            'active-tab on sw-tabs-item',
            '    <sw-tabs position-identifier="acme">',
            '        <sw-tabs-item name="general" :active-tab="currentTab">General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert labels with markup',
            'a label with markup in sw-tabs-item',
            '    <sw-tabs position-identifier="acme">',
            '        <sw-tabs-item name="general"><mt-icon name="regular-cog" /> General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert mixed labels',
            'a label with markup in sw-tabs-item',
            '    <sw-tabs position-identifier="acme">',
            "        <sw-tabs-item name=\"general\">{{ $t('acme.general') }} ({{ count }})</sw-tabs-item>",
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert other children',
            'a <div> element in sw-tabs',
            '    <sw-tabs position-identifier="acme">',
            '        <div>General</div>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert other slots',
            'the #header slot of sw-tabs',
            '    <sw-tabs position-identifier="acme">',
            '        <template #header>Header</template>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert routes without a static name',
            'a route without a static route name on sw-tabs-item',
            '    <sw-tabs position-identifier="acme">',
            '        <sw-tabs-item :route="generalRoute">General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert route paths',
            'a route path on sw-tabs-item',
            '    <sw-tabs position-identifier="acme">',
            '        <sw-tabs-item route="/acme/general">General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert names that differ from the route name',
            'a name that differs from the route name of sw-tabs-item',
            '    <sw-tabs position-identifier="acme">',
            "        <sw-tabs-item name=\"general\" :route=\"{ name: 'acme.general' }\">General</sw-tabs-item>",
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert route items with a default-item',
            'default-item on sw-tabs with route items',
            '    <sw-tabs position-identifier="acme" default-item="acme.general">',
            "        <sw-tabs-item :route=\"{ name: 'acme.general' }\">General</sw-tabs-item>",
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert titles that differ from the label',
            'a title that differs from the label of sw-tabs-item',
            '    <sw-tabs position-identifier="acme">',
            '        <sw-tabs-item name="general" title="Basic settings">General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert items without a label',
            'a sw-tabs-item without a label',
            '    <sw-tabs position-identifier="acme">',
            '        <sw-tabs-item name="general" />',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert items without a name',
            'a sw-tabs-item without name or route',
            '    <sw-tabs position-identifier="acme">',
            '        <sw-tabs-item>General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert unknown item attributes',
            'the ref attribute on sw-tabs-item',
            '    <sw-tabs position-identifier="acme">',
            '        <sw-tabs-item ref="general" name="general">General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert a ref on sw-tabs',
            'a ref on sw-tabs',
            '    <sw-tabs ref="tabs" position-identifier="acme">',
            '        <sw-tabs-item name="general">General</sw-tabs-item>',
            '    </sw-tabs>',
        ),
        manualCase(
            'does not convert the meteor compatibility path',
            'use-meteor-component or items on sw-tabs',
            '    <sw-tabs position-identifier="acme" use-meteor-component :items="tabs" />',
        ),
    ],
});
