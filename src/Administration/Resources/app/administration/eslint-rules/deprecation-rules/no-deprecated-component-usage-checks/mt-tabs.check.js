const { quote, readContentExpression } = require('../utils/template-expressions');

/**
 * @param {Array<{ label: string, name: string }>} items JS source of each value
 * @returns {string}
 */
function serializeItems(items) {
    if (items.length === 0) {
        return '[]';
    }

    const entries = items.map((item) => `    {\n        'label': ${item.label},\n        'name': ${item.name}\n    }`);

    return `[\n${entries.join(',\n')}\n]`;
}

/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtTabs = (context, node) => {
    const mtComponentName = 'mt-tabs';

    // Refactor the old usage of mt-tabs to mt-tabs after the migration to the new component
    if (node.name !== mtComponentName) {
        return;
    }

    // Check if component has attribute ":items"
    const itemsAttribute = node.startTag.attributes.find((attr) => {
        return attr.key?.argument?.name === 'items';
    });

    // Check if component has attribute "is-vertical"
    const isVerticalAttribute = node.startTag.attributes.find((attr) => {
        return attr.key?.name === 'is-vertical' ||
            attr.key?.name === 'isVertical' ||
            attr.key?.name === 'isvertical';
    });

    const isVerticalAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'bind' &&
            (
                attr.key?.argument?.name === 'is-vertical' ||
                attr.key?.argument?.name === 'isVertical' ||
                attr.key?.argument?.name === 'isvertical'
            );
    });

    // Check if component has attribute "align-right"
    const alignRightAttribute = node.startTag.attributes.find((attr) => {
        return attr.key?.name === 'align-right' ||
            attr.key?.name === 'alignRight' ||
            attr.key?.name === 'alignright';
    })

    const alignRightAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'bind' &&
            (
                attr.key?.argument?.name === 'align-right' ||
                attr.key?.argument?.name === 'alignRight' ||
                attr.key?.argument?.name === 'alignright'
            );
    });

    // Check if component uses slot "default" with shorthand syntax, e.g. <template #default="{ active }">
    const shorthandSyntaxSlot = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.startTag &&
            child.startTag.attributes.find((attr) => {
                return attr.key?.name?.name === 'slot' && attr.key?.argument?.name === 'default';
            });
    });

    // Check if component has children without a slot declaration
    const childrenWithoutSlot = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.name !== 'template';
    });

    if (shorthandSyntaxSlot && !itemsAttribute) {
        context.report({
            node,
            message: `[${mtComponentName}] The default slot usage in mt-tabs was removed and replaced with the property "items".`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                /**
                 * Get all previous slot children components with "sw-tabs-item" and keep following values in an array:
                 * - Text content inside the component
                 * - "name" attribute
                 */
                const slotChildren = shorthandSyntaxSlot.children.filter((child) => {
                    return child.type === 'VElement' && child.name === 'sw-tabs-item';
                });

                const slotChildrenValues = slotChildren.map((child) => {
                    const startTag = child.startTag;
                    const attributes = startTag.attributes;

                    const nameAttribute = attributes.find((attr) => attr.key?.name === 'name');
                    const nameAttributeValue = nameAttribute ? nameAttribute.value.value : null;
                    const rawTextContent = child.children.find((child) => child.type === 'VText')?.value;
                    // Remove line breaks and spaces from text content
                    const textContent = rawTextContent?.replace(/\n/g, '').trim();

                    // Check if attributes contain route or routeExpression
                    const routeAttribute = attributes.find((attr) => {
                        return attr?.key?.name === 'route'
                    });
                    const routeAttributeExpression = attributes.find((attr) => {
                        return attr.key?.name?.name === 'bind' && attr.key?.argument?.name === 'route';
                    })

                    let routeAttributeFallbackValue = 'TODO: change this property';

                    if (routeAttributeExpression) {
                        routeAttributeFallbackValue = context.getSourceCode().text.slice(
                            routeAttributeExpression.value.expression.range[0],
                            routeAttributeExpression.value.expression.range[1]
                        );
                    } else if (routeAttribute) {
                        routeAttributeFallbackValue = routeAttribute.value.value;
                    }

                    // Keep $t(...) and $tc(...) labels as expressions, so the label is still translated
                    const label = readContentExpression(context.getSourceCode(), child)?.code ?? quote(textContent ?? '');

                    return {
                        name: quote(nameAttributeValue ?? routeAttributeFallbackValue),
                        label,
                    };
                });

                const itemPropValue = serializeItems(slotChildrenValues);

                // Add items array to mt-tabs component as a property
                const rangeAfterStartTag = node.startTag?.range[0] + '<mt-tabs'.length;
                yield fixer.insertTextAfterRange([rangeAfterStartTag, rangeAfterStartTag], ` :items="${itemPropValue}"`);

                // Add a comment before the default slot to inform that this is not used anymore
                yield fixer.insertTextBeforeRange(shorthandSyntaxSlot.startTag.range, `<!-- TODO Codemod: This slot is not used anymore. Please use the "items" property instead. -->\n`);
            }
        });
    }

    if (childrenWithoutSlot && !itemsAttribute) {
        context.report({
            node,
            message: `[${mtComponentName}] The default slot usage in mt-tabs was removed and replaced with the property "items".`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                /**
                 * Get all previous slot children components with "sw-tabs-item" and keep following values in an array:
                 * - Text content inside the component
                 * - "name" attribute
                 */
                const slotChildren = node.children.filter((child) => {
                    return child.type === 'VElement' && child.name === 'sw-tabs-item';
                });

                const slotChildrenValues = slotChildren.map((child) => {
                    const startTag = child.startTag;
                    const attributes = startTag.attributes;

                    const nameAttribute = attributes.find((attr) => attr.key.name === 'name');
                    const nameAttributeValue = nameAttribute ? nameAttribute.value.value : null;
                    const rawTextContent = child.children.find((child) => child.type === 'VText')?.value;
                    // Remove line breaks and spaces from text content
                    const textContent = rawTextContent?.replace(/\n/g, '').trim();

                    // Check if attributes contain route or routeExpression
                    const routeAttribute = attributes.find((attr) => {
                        return attr?.key?.name === 'route'
                    });
                    const routeAttributeExpression = attributes.find((attr) => {
                        return attr?.key?.name?.name === 'bind' && attr?.key?.argument?.name === 'route';
                    })

                    let routeAttributeFallbackValue = 'TODO: change this property';

                    if (routeAttributeExpression) {
                        routeAttributeFallbackValue = context.getSourceCode().text.slice(
                            routeAttributeExpression.value.expression.range[0],
                            routeAttributeExpression.value.expression.range[1]
                        );
                    } else if (routeAttribute) {
                        routeAttributeFallbackValue = routeAttribute.value.value;
                    }

                    // Keep $t(...) and $tc(...) labels as expressions, so the label is still translated
                    const label = readContentExpression(context.getSourceCode(), child)?.code ?? quote(textContent ?? '');

                    return {
                        name: quote(nameAttributeValue ?? routeAttributeFallbackValue),
                        label,
                    };
                });

                const itemPropValue = serializeItems(slotChildrenValues);
                const indentation = ' '.repeat(node.startTag.loc.start.column);

                // Add items array to mt-tabs component as a property
                const rangeAfterStartTag = node.startTag?.range[0] + '<mt-tabs'.length;
                yield fixer.insertTextAfterRange([rangeAfterStartTag, rangeAfterStartTag], ` :items="${itemPropValue}"`);

                // Add a comment before the first child to inform that this is not used anymore
                yield fixer.insertTextBeforeRange(node.children[0].range, `<!-- TODO Codemod: This slot is not used anymore. Please use the "items" property instead. -->`);
            }
        });
    }

    if (isVerticalAttribute) {
        context.report({
            node,
            message: `[${mtComponentName}] The property "isVertical" was renamed to "vertical".`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(isVerticalAttribute.key, 'vertical');
            }
        });
    }

    if (isVerticalAttributeExpression) {
        context.report({
            node,
            message: `[${mtComponentName}] The property "isVertical" was renamed to "vertical".`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(isVerticalAttributeExpression.key.argument, 'vertical');
            }
        });
    }

    if (alignRightAttribute) {
        context.report({
            node,
            message: `[${mtComponentName}] The property "alignRight" was removed.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(alignRightAttribute);
            }
        });
    }

    if (alignRightAttributeExpression) {
        context.report({
            node,
            message: `[${mtComponentName}] The property "alignRight" was removed.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(alignRightAttributeExpression);
            }
        });
    }
}

const mtTabsValidTests = [
    {
        name: '"sw-tabs" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-tabs />
            </template>`
    },
    {
        name: '"mt-tabs" keeps the "content" slot, which the mt-tabs wrapper renders',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs :items="items">
                    <template #content="{ active }">
                        The current active item is {{ active }}
                    </template>
                </mt-tabs>
            </template>`,
    },
]

const mtTabsInvalidTests = [
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
                    <!-- TODO Codemod: This slot is not used anymore. Please use the "items" property instead. -->
<template #default="{ active }">
                        <sw-tabs-item name="tab1">Tab 1</sw-tabs-item>
                        <sw-tabs-item name="tab2">Tab 2</sw-tabs-item>
                    </template>
                </mt-tabs>
            </template>`,
        errors: [{ message: '[mt-tabs] The default slot usage in mt-tabs was removed and replaced with the property "items".'}]
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
        errors: [{ message: '[mt-tabs] The default slot usage in mt-tabs was removed and replaced with the property "items".'}]
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
    {
        'label': $tc('sw-cms.elements.general.config.tab.one'),
        'name': 'tab1'
    },
    {
        'label': $tc('sw-cms.elements.general.config.tab.two'),
        'name': 'tab2'
    }
]">
                    <!-- TODO Codemod: This slot is not used anymore. Please use the "items" property instead. -->
<template #default="{ active }">
                        <sw-tabs-item name="tab1">{{ $tc('sw-cms.elements.general.config.tab.one') }}</sw-tabs-item>
                        <sw-tabs-item name="tab2">{{ $tc('sw-cms.elements.general.config.tab.two') }}</sw-tabs-item>
                    </template>
                </mt-tabs>
            </template>`,
        errors: [{ message: '[mt-tabs] The default slot usage in mt-tabs was removed and replaced with the property "items".'}]
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
        errors: [{ message: '[mt-tabs] The default slot usage in mt-tabs was removed and replaced with the property "items".'}]
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
                <mt-tabs :items="[
    {
        'label': 'Tab 1',
        'name': 'tab1'
    },
    {
        'label': 'Tab 2',
        'name': 'tab2'
    }
]"><!-- TODO Codemod: This slot is not used anymore. Please use the "items" property instead. -->
                    <sw-tabs-item name="tab1">Tab 1</sw-tabs-item>
                    <sw-tabs-item name="tab2">Tab 2</sw-tabs-item>
                </mt-tabs>
            </template>`,
        errors: [{ message: '[mt-tabs] The default slot usage in mt-tabs was removed and replaced with the property "items".'}]
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
        errors: [{ message: '[mt-tabs] The property "isVertical" was renamed to "vertical".'}]
    },
    {
        name: '"mt-tabs" property "isVertical" was renamed to "vertical" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-tabs is-vertical />
            </template>`,
        errors: [{ message: '[mt-tabs] The property "isVertical" was renamed to "vertical".'}]
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
        errors: [{ message: '[mt-tabs] The property "isVertical" was renamed to "vertical".'}]
    },
    {
        name: '"mt-tabs" property "isVertical" was renamed to "vertical" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-tabs :is-vertical="true" />
            </template>`,
        errors: [{ message: '[mt-tabs] The property "isVertical" was renamed to "vertical".'}]
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
                <mt-tabs  />
            </template>`,
        errors: [{ message: '[mt-tabs] The property "alignRight" was removed.'}]
    },
    {
        name: '"mt-tabs" property "alignRight" was removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-tabs align-right />
            </template>`,
        errors: [{ message: '[mt-tabs] The property "alignRight" was removed.'}]
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
                <mt-tabs  />
            </template>`,
        errors: [{ message: '[mt-tabs] The property "alignRight" was removed.'}]
    },
    {
        name: '"mt-tabs" property "alignRight" was removed [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-tabs :align-right="true" />
            </template>`,
        errors: [{ message: '[mt-tabs] The property "alignRight" was removed.'}]
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
        output: `
            <template>
                <mt-tabs :items="[
    {
        'label': $tc('sw-product.detail.tabGeneral'),
        'name': 'sw.product.detail.base'
    },
    {
        'label': $tc('sw-product.detail.tabSpecifications'),
        'name': '{ name: \\'sw.product.detail.specifications\\', params: { id: $route.params.id } }'
    }
]"
                    v-if="productId"
                    class="sw-product-detail-page__tabs"
                    position-identifier="sw-product-detail"
                ><!-- TODO Codemod: This slot is not used anymore. Please use the "items" property instead. -->
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
        errors: [
            { message: '[mt-tabs] The default slot usage in mt-tabs was removed and replaced with the property "items".' },
        ]
    },
    {
        name: '"mt-tabs" keeps double-quoted snippet labels as expressions in .vue files',
        filename: 'test.vue',
        code: `
            <template>
                <mt-tabs>
                    <sw-tabs-item name="tab1">{{ $t("sw-cms.tab.one") }}</sw-tabs-item>
                </mt-tabs>
            </template>`,
        output: `
            <template>
                <mt-tabs :items="[
    {
        'label': $t('sw-cms.tab.one'),
        'name': 'tab1'
    }
]"><!-- TODO Codemod: This slot is not used anymore. Please use the "items" property instead. -->
                    <sw-tabs-item name="tab1">{{ $t("sw-cms.tab.one") }}</sw-tabs-item>
                </mt-tabs>
            </template>`,
        errors: [
            { message: '[mt-tabs] The default slot usage in mt-tabs was removed and replaced with the property "items".' },
        ]
    },
];

module.exports = {
    handleMtTabs,
    mtTabsValidTests,
    mtTabsInvalidTests,
};
