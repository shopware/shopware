/**
 * @sw-package framework
 */

const manualWrapperComponents = [
    'sw-meteor-card',
    'sw-meteor-page',
    'sw-extension-component-section',
];

const smallTodo = 'TODO Codemod: The "small" prop is deprecated on mt-tabs. Check the surrounding layout after this migration.';
const alignRightTodo = 'TODO Codemod: The "align-right" prop has no mt-tabs equivalent and was removed. Check the tab alignment manually.';

function normalizeName(name) {
    return name?.replace(/-/g, '').toLowerCase();
}

function sourceOf(context, node) {
    return context.getSourceCode().text.slice(node.range[0], node.range[1]);
}

function isDirective(attr, name) {
    return attr.directive === true && attr.key?.name?.name === name;
}

function attributeName(attr) {
    if (attr.directive !== true) {
        return attr.key?.name;
    }

    return attr.key?.argument?.name ?? attr.key?.name?.name;
}

function hasName(attr, names) {
    return names.includes(normalizeName(attributeName(attr)));
}

function getAttribute(attributes, names) {
    return attributes.find((attr) => hasName(attr, names));
}

function getExpressionSource(context, attr) {
    if (!attr?.value) {
        return null;
    }

    if (attr.directive === true) {
        const expressionRange = attr.value.expression?.range;

        if (!expressionRange) {
            return null;
        }

        return context.getSourceCode().text.slice(expressionRange[0], expressionRange[1]);
    }

    return stringLiteral(attr.value.value);
}

function stringLiteral(value) {
    return `'${`${value}`.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function escapeAttributeValue(value) {
    return value.replace(/"/g, '&quot;');
}

function isWhitespaceText(node) {
    return node.type === 'VText' && node.value.trim() === '';
}

function isSlotTemplate(node, slotName) {
    if (node.type !== 'VElement' || node.name !== 'template') {
        return false;
    }

    return node.startTag.attributes.some((attr) => {
        if (isDirective(attr, 'slot')) {
            return attr.key?.argument?.name === slotName;
        }

        return attr.key?.name === 'slot' && attr.value?.value === slotName;
    });
}

function hasAncestor(node, names) {
    let current = node.parent;

    while (current) {
        if (current.type === 'VElement' && names.includes(current.name)) {
            return true;
        }

        current = current.parent;
    }

    return false;
}

function isEventAttribute(attr) {
    return isDirective(attr, 'on');
}

function getEventName(attr) {
    return normalizeName(attr.key?.argument?.name ?? '');
}

function findWrapperManualReasons(node) {
    const attributes = node.startTag.attributes;
    const reasons = [];

    if (hasAncestor(node, manualWrapperComponents)) {
        reasons.push('wrapper component tab integrations need manual migration');
    }

    const hasContentSlot = node.children.some((child) => isSlotTemplate(child, 'content'));

    if (hasContentSlot) {
        reasons.push('content slots need manual active-tab state migration');
    }

    if (node.children.some((child) => child.type === 'VElement' && child.name === 'template' && !isSlotTemplate(child, 'content'))) {
        reasons.push('template slot children need manual migration');
    }

    if (attributes.some((attr) => isEventAttribute(attr) && getEventName(attr) === 'newitemactive')) {
        reasons.push('old "new-item-active" handlers need manual payload migration');
    }

    if (attributes.some((attr) => isEventAttribute(attr) && getEventName(attr) !== 'newitemactive')) {
        reasons.push('event listeners need manual migration');
    }

    if (attributes.some((attr) => isDirective(attr, 'for'))) {
        reasons.push('dynamic tab lists need manual item builders');
    }

    if (attributes.some((attr) => hasName(attr, [
        'active',
        'activetab',
    ]))) {
        reasons.push('old active tab props need manual state migration');
    }

    const unsupportedAttribute = attributes.find((attr) => {
        if (isEventAttribute(attr)) {
            return false;
        }

        if (isDirective(attr, 'for')) {
            return true;
        }

        if (isDirective(attr, 'if') || isDirective(attr, 'show') || isDirective(attr, 'else') || isDirective(attr, 'else-if')) {
            return false;
        }

        const name = normalizeName(attributeName(attr));

        if ([
            'positionidentifier',
            'defaultitem',
            'isvertical',
            'alignright',
            'small',
            'items',
            'class',
            'style',
            'ref',
            'key',
            'id',
        ].includes(name)) {
            return false;
        }

        return !attributeName(attr)?.startsWith('data-');
    });

    if (unsupportedAttribute) {
        reasons.push(`unsupported "${attributeName(unsupportedAttribute)}" attribute needs manual migration`);
    }

    return [...new Set(reasons)];
}

function findItemManualReason(item) {
    const attributes = item.startTag.attributes;

    if (attributes.some((attr) => isDirective(attr, 'for'))) {
        return 'dynamic "v-for" tab items need manual item builders';
    }

    if (attributes.some((attr) => hasName(attr, [
        'route',
    ]))) {
        return 'route tabs need manual onClick migration';
    }

    if (attributes.some((attr) => isEventAttribute(attr))) {
        return 'custom tab item listeners need manual migration';
    }

    if (attributes.some((attr) => hasName(attr, [
        'active',
        'activetab',
    ]))) {
        return 'old active tab item props need manual state migration';
    }

    if (attributes.some((attr) => hasName(attr, [
        'haswarning',
    ]))) {
        return '"has-warning" needs manual badge mapping';
    }

    const unsupportedAttribute = attributes.find((attr) => {
        if (isDirective(attr, 'for') || isEventAttribute(attr)) {
            return true;
        }

        return !hasName(attr, [
            'name',
            'title',
            'haserror',
            'disabled',
        ]);
    });

    if (unsupportedAttribute) {
        return `unsupported "${attributeName(unsupportedAttribute)}" tab item attribute needs manual migration`;
    }

    if (!getAttribute(attributes, ['name'])) {
        return 'tab items without a "name" need manual migration';
    }

    return null;
}

function labelFromText(context, item) {
    if (item.children.some((child) => child.type !== 'VText')) {
        return {
            reason: 'complex tab item content needs manual label migration',
        };
    }

    const text = item.children.map((child) => child.value).join('').trim();

    if (!text) {
        return {
            reason: 'tab items without a label need manual migration',
        };
    }

    const interpolation = text.match(/^\{\{\s*([\s\S]*?)\s*\}\}$/);

    if (interpolation) {
        return {
            source: interpolation[1].trim(),
        };
    }

    if (text.includes('{{')) {
        return {
            reason: 'mixed tab item text needs manual label migration',
        };
    }

    return {
        source: stringLiteral(text),
    };
}

function itemEntryFromNode(context, item) {
    const attributes = item.startTag.attributes;
    const manualReason = findItemManualReason(item);

    if (manualReason) {
        return {
            reason: manualReason,
        };
    }

    const titleAttribute = getAttribute(attributes, ['title']);
    const label = titleAttribute ? { source: getExpressionSource(context, titleAttribute) } : labelFromText(context, item);

    if (label.reason) {
        return label;
    }

    if (!label.source) {
        return {
            reason: 'tab items without a label need manual migration',
        };
    }

    const name = getExpressionSource(context, getAttribute(attributes, ['name']));

    if (!name) {
        return {
            reason: 'tab items without a "name" need manual migration',
        };
    }

    const entry = [
        ['label', label.source],
        ['name', name],
    ];

    const hasError = getAttribute(attributes, ['haserror']);
    if (hasError) {
        entry.push(['hasError', hasError.value ? getExpressionSource(context, hasError) : 'true']);
    }

    const disabled = getAttribute(attributes, ['disabled']);
    if (disabled) {
        entry.push(['disabled', disabled.value ? getExpressionSource(context, disabled) : 'true']);
    }

    return {
        entry,
    };
}

function buildItems(entries, indent) {
    if (entries.length === 0) {
        return '[]';
    }

    const itemIndent = `${indent}        `;
    const propertyIndent = `${indent}            `;
    const lines = ['['];

    entries.forEach(({ entry }) => {
        lines.push(`${itemIndent}{`);
        entry.forEach(([key, value]) => {
            lines.push(`${propertyIndent}${key}: ${value},`);
        });
        lines.push(`${itemIndent}},`);
    });

    lines.push(`${indent}    ]`);

    return lines.join('\n');
}

function buildWrapperAttribute(context, attr) {
    if (hasName(attr, ['isvertical'])) {
        if (attr.directive === true) {
            return `:vertical="${escapeAttributeValue(getExpressionSource(context, attr))}"`;
        }

        return attr.value ? `vertical=${sourceOf(context, attr.value)}` : 'vertical';
    }

    return sourceOf(context, attr);
}

function buildReplacement(context, node, itemEntries) {
    const indent = ' '.repeat(node.loc.start.column);
    const attributes = node.startTag.attributes;
    const hasItemsAttribute = Boolean(getAttribute(attributes, ['items']));
    const todoComments = [];
    const wrapperAttributes = [];

    attributes.forEach((attr) => {
        if (hasName(attr, ['alignright'])) {
            todoComments.push(alignRightTodo);
            return;
        }

        if (hasName(attr, ['small'])) {
            todoComments.push(smallTodo);
        }

        wrapperAttributes.push(buildWrapperAttribute(context, attr));
    });

    const lines = [...new Set(todoComments)].map((comment, index) => {
        return `${index === 0 ? '' : indent}<!-- ${comment} -->`;
    });

    lines.push(`${lines.length === 0 ? '' : indent}<mt-tabs`);
    wrapperAttributes.forEach((attrSource) => {
        lines.push(`${indent}    ${attrSource}`);
    });

    if (!hasItemsAttribute) {
        lines.push(`${indent}    :items="${escapeAttributeValue(buildItems(itemEntries, indent))}"`);
    }

    lines.push(`${indent}/>`);

    return lines.join('\n');
}

/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleSwTabs = (context, node) => {
    const componentName = 'sw-tabs';

    if (node.name === 'sw-tabs-item' && !hasAncestor(node, [
        componentName,
        'mt-tabs',
    ])) {
        context.report({
            node,
            message: '"sw-tabs-item" is deprecated. Please define tab entries through the "items" property on "mt-tabs" instead.',
        });
        return;
    }

    if (node.name !== componentName) {
        return;
    }

    const meaningfulChildren = node.children.filter((child) => !isWhitespaceText(child));
    const tabItemChildren = meaningfulChildren.filter((child) => {
        return child.type === 'VElement' && child.name === 'sw-tabs-item';
    });
    const wrapperManualReasons = findWrapperManualReasons(node);

    const unsupportedChildren = meaningfulChildren.filter((child) => {
        return child.type !== 'VElement' || ![
            'sw-tabs-item',
            'template',
        ].includes(child.name);
    });

    if (unsupportedChildren.length > 0) {
        wrapperManualReasons.push('non-tab children need manual migration');
    }

    if (getAttribute(node.startTag.attributes, ['items']) && tabItemChildren.length > 0) {
        wrapperManualReasons.push('mixed "items" prop and slot children need manual migration');
    }

    const itemEntries = tabItemChildren.map((item) => itemEntryFromNode(context, item));
    const itemManualReason = itemEntries.find((entry) => entry.reason)?.reason;
    const manualReasons = [...new Set([
        ...wrapperManualReasons,
        itemManualReason,
    ].filter(Boolean))];

    if (manualReasons.length > 0) {
        context.report({
            node,
            message: `[${componentName}] Cannot automatically migrate to "mt-tabs": ${manualReasons.join('; ')}.`,
        });
        return;
    }

    context.report({
        node,
        message: `"${componentName}" is deprecated. Please use "mt-tabs" with the "items" property instead.`,
        *fix(fixer) {
            if (context.options.includes('disableFix')) return;

            yield fixer.replaceText(node, buildReplacement(context, node, itemEntries));
        },
    });
};

const swTabsValidTests = [
    {
        name: '"mt-tabs" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-tabs :items="items" />
            </template>`,
    },
    {
        name: '"sw-tabs" usage is ignored until the sw-tabs migration is enabled',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-tabs />
            </template>`,
    },
];

const swTabsInvalidTests = [
    {
        name: '"sw-tabs" static item usage is migrated to "mt-tabs" items',
        filename: 'test.html.twig',
        options: ['migrateSwTabs'],
        code: `
<template>
    <sw-tabs
        position-identifier="example-tabs"
        default-item="general"
        is-vertical
    >
        <sw-tabs-item
            name="general"
            :title="$t('example.general')"
            :has-error="hasGeneralError"
            :disabled="isDisabled"
        >
            {{ $t('example.general') }}
        </sw-tabs-item>
    </sw-tabs>
</template>`,
        output: `
<template>
    <mt-tabs
        position-identifier="example-tabs"
        default-item="general"
        vertical
        :items="[
            {
                label: $t('example.general'),
                name: 'general',
                hasError: hasGeneralError,
                disabled: isDisabled,
            },
        ]"
    />
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" text labels are migrated to "mt-tabs" items',
        filename: 'test.html.twig',
        options: ['migrateSwTabs'],
        code: `
<template>
    <sw-tabs>
        <sw-tabs-item name="general">General</sw-tabs-item>
        <sw-tabs-item :name="advancedName" title="Advanced" />
    </sw-tabs>
</template>`,
        output: `
<template>
    <mt-tabs
        :items="[
            {
                label: 'General',
                name: 'general',
            },
            {
                label: 'Advanced',
                name: advancedName,
            },
        ]"
    />
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" align-right is removed with a TODO and small is kept with a TODO',
        filename: 'test.html.twig',
        options: ['migrateSwTabs'],
        code: `
<template>
    <sw-tabs small align-right>
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        output: `
<template>
    <!-- TODO Codemod: The "small" prop is deprecated on mt-tabs. Check the surrounding layout after this migration. -->
    <!-- TODO Codemod: The "align-right" prop has no mt-tabs equivalent and was removed. Check the tab alignment manually. -->
    <mt-tabs
        small
        :items="[
            {
                label: 'General',
                name: 'general',
            },
        ]"
    />
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" existing items prop is migrated without generating items',
        filename: 'test.html.twig',
        options: ['migrateSwTabs'],
        code: `
<template>
    <sw-tabs
        position-identifier="example-tabs"
        :items="tabs"
    />
</template>`,
        output: `
<template>
    <mt-tabs
        position-identifier="example-tabs"
        :items="tabs"
    />
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" route tabs are reported for manual migration',
        filename: 'test.html.twig',
        options: ['migrateSwTabs'],
        code: `
<template>
    <sw-tabs position-identifier="example-tabs">
        <sw-tabs-item
            name="general"
            route="sw.product.detail.base"
        >
            General
        </sw-tabs-item>
    </sw-tabs>
</template>`,
        errors: [{
            message: '[sw-tabs] Cannot automatically migrate to "mt-tabs": route tabs need manual onClick migration.',
        }],
    },
    {
        name: '"sw-tabs" content slots are reported for manual migration',
        filename: 'test.html.twig',
        options: ['migrateSwTabs'],
        code: `
<template>
    <sw-tabs position-identifier="example-tabs">
        <sw-tabs-item name="general">General</sw-tabs-item>

        <template #content="{ active }">
            {{ active }}
        </template>
    </sw-tabs>
</template>`,
        errors: [{
            message: '[sw-tabs] Cannot automatically migrate to "mt-tabs": content slots need manual active-tab state migration.',
        }],
    },
    {
        name: '"sw-tabs" dynamic item lists are reported for manual migration',
        filename: 'test.html.twig',
        options: ['migrateSwTabs'],
        code: `
<template>
    <sw-tabs position-identifier="example-tabs">
        <sw-tabs-item
            v-for="item in items"
            :key="item.name"
            :name="item.name"
            :title="item.label"
        />
    </sw-tabs>
</template>`,
        errors: [{
            message: '[sw-tabs] Cannot automatically migrate to "mt-tabs": dynamic "v-for" tab items need manual item builders.',
        }],
    },
    {
        name: '"sw-tabs" new-item-active listeners are reported for manual migration',
        filename: 'test.html.twig',
        options: ['migrateSwTabs'],
        code: `
<template>
    <sw-tabs
        position-identifier="example-tabs"
        @new-item-active="setActiveItem"
    >
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        errors: [{
            message: '[sw-tabs] Cannot automatically migrate to "mt-tabs": old "new-item-active" handlers need manual payload migration.',
        }],
    },
    {
        name: '"sw-tabs" has-warning items are reported for manual migration',
        filename: 'test.html.twig',
        options: ['migrateSwTabs'],
        code: `
<template>
    <sw-tabs position-identifier="example-tabs">
        <sw-tabs-item
            name="general"
            has-warning
        >
            General
        </sw-tabs-item>
    </sw-tabs>
</template>`,
        errors: [{
            message: '[sw-tabs] Cannot automatically migrate to "mt-tabs": "has-warning" needs manual badge mapping.',
        }],
    },
];

module.exports = {
    handleSwTabs,
    swTabsValidTests,
    swTabsInvalidTests,
};
