const swEntityListingValidTests = [
    {
        name: '"sw-entity-listing" with data-source prop is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-entity-listing
                    :data-source="entityList"
                    :repository="entityRepository"
                    :columns="columns"
                />
            </template>`
    },
    {
        name: '"sw-entity-listing" without items prop is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-entity-listing
                    :repository="entityRepository"
                    :columns="columns"
                />
            </template>`
    },
]

const swEntityListingInvalidTests = [
    {
        name: '"sw-entity-listing" with deprecated items prop',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-entity-listing
                    :items="entityList"
                    :repository="entityRepository"
                    :columns="columns"
                />
            </template>`,
        output: `
            <template>
                <sw-entity-listing
                    :data-source="entityList"
                    :repository="entityRepository"
                    :columns="columns"
                />
            </template>`,
        errors: [{
            message: '[sw-entity-listing] The "items" API is deprecated. Use "data-source" instead.',
        }]
    },
    {
        name: '"sw-entity-listing" with deprecated items prop [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <sw-entity-listing
                    :items="entityList"
                    :repository="entityRepository"
                    :columns="columns"
                />
            </template>`,
        errors: [{
            message: '[sw-entity-listing] The "items" API is deprecated. Use "data-source" instead.',
        }]
    },
    {
        name: '"sw-entity-listing" with deprecated items prop [single line]',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-entity-listing :items="taxList" :repository="taxRepository" :columns="columns" />
            </template>`,
        output: `
            <template>
                <sw-entity-listing :data-source="taxList" :repository="taxRepository" :columns="columns" />
            </template>`,
        errors: [{
            message: '[sw-entity-listing] The "items" API is deprecated. Use "data-source" instead.',
        }]
    },
    {
        name: '"sw-entity-listing" with deprecated items prop [multiple props]',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-entity-listing
                    ref="swSettingsTaxGrid"
                    class="sw-settings-tax-list-grid"
                    detail-route="sw.settings.tax.detail"
                    :items="tax"
                    :columns="getTaxColumns()"
                    :repository="taxRepository"
                    :full-page="false"
                    :show-selection="false"
                />
            </template>`,
        output: `
            <template>
                <sw-entity-listing
                    ref="swSettingsTaxGrid"
                    class="sw-settings-tax-list-grid"
                    detail-route="sw.settings.tax.detail"
                    :data-source="tax"
                    :columns="getTaxColumns()"
                    :repository="taxRepository"
                    :full-page="false"
                    :show-selection="false"
                />
            </template>`,
        errors: [{
            message: '[sw-entity-listing] The "items" API is deprecated. Use "data-source" instead.',
        }]
    },
];

module.exports = {
    valid: swEntityListingValidTests,
    invalid: swEntityListingInvalidTests,
};
