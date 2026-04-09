/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleSwEntityListing = (context, node) => {
    const swEntityListingComponentName = 'sw-entity-listing';

    // Check for deprecated usage of items prop in sw-entity-listing
    if (node.name !== swEntityListingComponentName) {
        return;
    }

    const startTag = node.startTag;
    const attributes = startTag.attributes;

    // Attribute checks
    const isBindAttribute = (attr) => attr.type === 'VAttribute' && attr.key.name.name === 'bind';
    
    const itemsAttribute = attributes.find((attr) => {
        // Check for bind attribute
        if (isBindAttribute(attr)) {
            return attr?.key?.argument?.name === 'items';
        }

        return attr.key.name === 'items';
    });

    // Check if items prop is used
    if (itemsAttribute) {
        context.report({
            node: itemsAttribute,
            message: '[sw-entity-listing] The "items" prop is deprecated and will be removed in v6.8.0. Please use "data-source" instead.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                // Replace :items with :data-source or items with data-source
                if (isBindAttribute(itemsAttribute)) {
                    // Handle :items="..." case
                    const keyRange = itemsAttribute.key.range;
                    yield fixer.replaceTextRange(
                        [keyRange[0], keyRange[1]],
                        ':data-source'
                    );
                } else {
                    // Handle items="..." case (unlikely but possible)
                    const keyRange = itemsAttribute.key.range;
                    yield fixer.replaceTextRange(
                        keyRange,
                        'data-source'
                    );
                }
            }
        });
    }
}

const swEntityListingValidChecks = [
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

const swEntityListingInvalidChecks = [
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
            message: '[sw-entity-listing] The "items" prop is deprecated and will be removed in v6.8.0. Please use "data-source" instead.',
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
            message: '[sw-entity-listing] The "items" prop is deprecated and will be removed in v6.8.0. Please use "data-source" instead.',
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
            message: '[sw-entity-listing] The "items" prop is deprecated and will be removed in v6.8.0. Please use "data-source" instead.',
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
            message: '[sw-entity-listing] The "items" prop is deprecated and will be removed in v6.8.0. Please use "data-source" instead.',
        }]
    },
];

module.exports = {
    swEntityListingValidChecks,
    swEntityListingInvalidChecks,
    handleSwEntityListing
};
