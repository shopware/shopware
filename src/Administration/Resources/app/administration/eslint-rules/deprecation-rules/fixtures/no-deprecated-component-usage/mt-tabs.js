const mtTabsValidTests = [
    {
        name: '"sw-tabs" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-tabs />
            </template>`,
    },
];

const ITEMS_ALREADY_SET =
    '[mt-tabs] The "default" slot is removed. Pass its <sw-tabs-item> elements as the "items" prop.\n' +
    'The element already sets "items". Move the <sw-tabs-item> elements into the "items" prop manually.';

const mtTabsInvalidTests = [
    {
        name: '"mt-tabs" default slot is left for a manual move when the items prop already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs items="existing">
                    <template #default="{ active }">
                        <sw-tabs-item name="tab1">Tab 1</sw-tabs-item>
                    </template>
                </mt-tabs>
            </template>`,
        output: null,
        errors: [{ message: ITEMS_ALREADY_SET }],
    },
    {
        name: '"mt-tabs" direct children are left for a manual move when the items prop already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs items="existing">
                    <sw-tabs-item name="tab1">Tab 1</sw-tabs-item>
                </mt-tabs>
            </template>`,
        output: null,
        errors: [{ message: ITEMS_ALREADY_SET }],
    },
    {
        name: '"mt-tabs" wrong "default" slot usage will be replaced with "items" property',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs>
                    <template #default="{ active }">
                        <sw-tabs-item name="tab1">Tab 1</sw-tabs-item>
                        <sw-tabs-item name="tab2">Tab 2</sw-tabs-item>
                    </template>
                </mt-tabs>
            </template>`,
        output: `
            <template>
                <mt-tabs :items="[{ label: 'Tab 1', name: 'tab1' }, { label: 'Tab 2', name: 'tab2' }]" />
            </template>`,
        errors: [
            { message: '[mt-tabs] The "default" slot is removed. Pass its <sw-tabs-item> elements as the "items" prop.' },
        ],
    },
    {
        name: '"mt-tabs" wrong "default" slot usage will be replaced with "items" property [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-tabs>
                    <template #default="{ active }">
                        <sw-tabs-item name="tab1">Tab 1</sw-tabs-item>
                        <sw-tabs-item name="tab2">Tab 2</sw-tabs-item>
                    </template>
                </mt-tabs>
            </template>`,
        errors: [
            { message: '[mt-tabs] The "default" slot is removed. Pass its <sw-tabs-item> elements as the "items" prop.' },
        ],
    },
    {
        name: '"mt-tabs" wrong "default" slot usage will be replaced with "items" property [with $tc snippet]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs>
                    <template #default="{ active }">
                        <sw-tabs-item name="tab1">{{ $tc('sw-cms.elements.general.config.tab.one') }}</sw-tabs-item>
                        <sw-tabs-item name="tab2">{{ $tc('sw-cms.elements.general.config.tab.two') }}</sw-tabs-item>
                    </template>
                </mt-tabs>
            </template>`,
        output: `
            <template>
                <mt-tabs :items="[
                    { label: $tc('sw-cms.elements.general.config.tab.one'), name: 'tab1' },
                    { label: $tc('sw-cms.elements.general.config.tab.two'), name: 'tab2' },
                ]" />
            </template>`,
        errors: [
            { message: '[mt-tabs] The "default" slot is removed. Pass its <sw-tabs-item> elements as the "items" prop.' },
        ],
    },
    {
        name: '"mt-tabs" wrong "default" slot usage will be replaced with "items" property [with $tc snippet, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-tabs>
                    <template #default="{ active }">
                        <sw-tabs-item name="tab1">{{ $tc('sw-cms.elements.general.config.tab.one') }}</sw-tabs-item>
                        <sw-tabs-item name="tab2">{{ $tc('sw-cms.elements.general.config.tab.two') }}</sw-tabs-item>
                    </template>
                </mt-tabs>
            </template>`,
        errors: [
            { message: '[mt-tabs] The "default" slot is removed. Pass its <sw-tabs-item> elements as the "items" prop.' },
        ],
    },
    {
        name: '"mt-tabs" wrong "default" slot usage will be replaced with "items" property [without direct slot declaration]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs>
                    <sw-tabs-item name="tab1">Tab 1</sw-tabs-item>
                    <sw-tabs-item name="tab2">Tab 2</sw-tabs-item>
                </mt-tabs>
            </template>`,
        output: `
            <template>
                <mt-tabs :items="[{ label: 'Tab 1', name: 'tab1' }, { label: 'Tab 2', name: 'tab2' }]" />
            </template>`,
        errors: [
            { message: '[mt-tabs] The "default" slot is removed. Pass its <sw-tabs-item> elements as the "items" prop.' },
        ],
    },
    {
        name: '"mt-tabs" wrong "content" slot usage - content should be set manually outside the component',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs>
                    <template #content="{ active }">
                        The current active item is {{ active }}
                    </template>
                </mt-tabs>
            </template>`,
        output: null,
        errors: [
            {
                message:
                    '[mt-tabs] Move content slot markup out of mt-tabs and render it next to the active tab state.',
            },
        ],
    },
    {
        name: '"mt-tabs" wrong "content" slot usage - content should be set manually outside the component [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-tabs>
                    <template #content="{ active }">
                        The current active item is {{ active }}
                    </template>
                </mt-tabs>
            </template>`,
        errors: [
            {
                message:
                    '[mt-tabs] Move content slot markup out of mt-tabs and render it next to the active tab state.',
            },
        ],
    },
    {
        name: '"mt-tabs" property "isVertical" was renamed to "vertical"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs is-vertical />
            </template>`,
        output: `
            <template>
                <mt-tabs vertical />
            </template>`,
        errors: [{ message: '[mt-tabs] The "is-vertical" API is deprecated. Use "vertical" instead.' }],
    },
    {
        name: '"mt-tabs" property "isVertical" was renamed to "vertical" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-tabs is-vertical />
            </template>`,
        errors: [{ message: '[mt-tabs] The "is-vertical" API is deprecated. Use "vertical" instead.' }],
    },
    {
        name: '"mt-tabs" property "isVertical" is removed when "vertical" already passes the same value',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs vertical is-vertical />
            </template>`,
        output: `
            <template>
                <mt-tabs vertical />
            </template>`,
        errors: [{ message: '[mt-tabs] The "is-vertical" API is deprecated. Use "vertical" instead.\nThe element already passes "vertical" with the same value, so the deprecated attribute is removed.' }],
    },
    {
        name: '"mt-tabs" property "isVertical" was renamed to "vertical" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs :is-vertical="true" />
            </template>`,
        output: `
            <template>
                <mt-tabs :vertical="true" />
            </template>`,
        errors: [{ message: '[mt-tabs] The "is-vertical" API is deprecated. Use "vertical" instead.' }],
    },
    {
        name: '"mt-tabs" property "isVertical" expression should not be fixed when "vertical" already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs :vertical="foo" :is-vertical="bar" />
            </template>`,
        output: null,
        errors: [{ message: '[mt-tabs] The "is-vertical" API is deprecated. Use "vertical" instead.' }],
    },
    {
        name: '"mt-tabs" property "isVertical" was renamed to "vertical" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-tabs :is-vertical="true" />
            </template>`,
        errors: [{ message: '[mt-tabs] The "is-vertical" API is deprecated. Use "vertical" instead.' }],
    },
    {
        name: '"mt-tabs" property "alignRight" was removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs align-right />
            </template>`,
        output: `
            <template>
                <mt-tabs />
            </template>`,
        errors: [{ message: '[mt-tabs] The "align-right" API is deprecated.' }],
    },
    {
        name: '"mt-tabs" property "alignRight" was removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-tabs align-right />
            </template>`,
        errors: [{ message: '[mt-tabs] The "align-right" API is deprecated.' }],
    },
    {
        name: '"mt-tabs" property "alignRight" was removed [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs :align-right="true" />
            </template>`,
        output: `
            <template>
                <mt-tabs />
            </template>`,
        errors: [{ message: '[mt-tabs] The "align-right" API is deprecated.' }],
    },
    {
        name: '"mt-tabs" property "alignRight" was removed [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-tabs :align-right="true" />
            </template>`,
        errors: [{ message: '[mt-tabs] The "align-right" API is deprecated.' }],
    },
    {
        name: '"mt-tabs" handle complex scenario',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs
                    v-if="productId"
                    class="sw-product-detail-page__tabs"
                    position-identifier="sw-product-detail"
                >
                    <sw-tabs-item
                        class="sw-product-detail__tab-general"
                        route="sw.product.detail.base"
                        :has-error="swProductDetailBaseError"
                        :title="$tc('sw-product.detail.tabGeneral')"
                    >
                        {{ $tc('sw-product.detail.tabGeneral') }}
                    </sw-tabs-item>

                    <sw-tabs-item
                        class="sw-product-detail__tab-specifications"
                        :route="{ name: 'sw.product.detail.specifications', params: { id: $route.params.id } }"
                        :title="$tc('sw-product.detail.tabSpecifications')"
                    >
                        {{ $tc('sw-product.detail.tabSpecifications') }}
                    </sw-tabs-item>
                </mt-tabs>
            </template>`,
        output: null,
        errors: [
            { message: '[mt-tabs] The "default" slot is removed. Pass its <sw-tabs-item> elements as the "items" prop.' },
        ],
    },
    {
        name: '"mt-tabs" keeps slashes and parentheses of item routes and escapes their quotes',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs>
                    <sw-tabs-item :route="getRoute('general/tab')">General</sw-tabs-item>
                </mt-tabs>
            </template>`,
        output: null,
        errors: [
            { message: '[mt-tabs] The "default" slot is removed. Pass its <sw-tabs-item> elements as the "items" prop.' },
        ],
    },
];

module.exports = {
    valid: mtTabsValidTests,
    invalid: mtTabsInvalidTests,
};
