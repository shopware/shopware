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
    return Boolean(attr && isDirective(attr, 'on'));
}

function getEventName(attr) {
    return normalizeName(attr.key?.argument?.name ?? '');
}

function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function getSlotScopeVariable(context, template, slotName, propertyName) {
    const slotAttribute = template.startTag.attributes.find((attr) => {
        if (isDirective(attr, 'slot')) {
            return attr.key?.argument?.name === slotName;
        }

        return attr.key?.name === 'slot' && attr.value?.value === slotName;
    });
    const expressionSource = getExpressionSource(context, slotAttribute)?.trim();

    if (!expressionSource?.startsWith('{') || !expressionSource.endsWith('}')) {
        return null;
    }

    const properties = expressionSource.slice(1, -1).split(',');
    const property = properties.find((entry) => new RegExp(`^${propertyName}(?:\\s*:|\\s*$)`).test(entry.trim()));
    const propertyMatch = property?.trim().match(new RegExp(`^${propertyName}(?:\\s*:\\s*([A-Za-z_$][\\w$]*))?$`));

    return propertyMatch ? propertyMatch[1] ?? propertyName : null;
}

function getDefaultSlotInfo(context, node) {
    const defaultSlotTemplates = node.children.filter((child) => isSlotTemplate(child, 'default'));

    if (defaultSlotTemplates.length !== 1) {
        return null;
    }

    const template = defaultSlotTemplates[0];
    const meaningfulChildren = template.children.filter((child) => !isWhitespaceText(child));
    const tabItemChildren = meaningfulChildren.filter((child) => child.type === 'VElement' && child.name === 'sw-tabs-item');
    const unsupportedChildren = meaningfulChildren.filter((child) => child.type !== 'VElement' || child.name !== 'sw-tabs-item');

    return {
        activeVariable: getSlotScopeVariable(context, template, 'default', 'active'),
        supported: tabItemChildren.length > 0 && unsupportedChildren.length === 0,
        tabItemChildren,
        template,
    };
}

function getMigratedNewItemActiveHandler(context, attr) {
    if (!isEventAttribute(attr) || getEventName(attr) !== 'newitemactive' || attr.key?.modifiers?.length > 0) {
        return null;
    }

    const expressionSource = getExpressionSource(context, attr);

    if (!expressionSource) {
        return null;
    }

    const trimmedExpression = expressionSource.trim();
    const stateAssignmentPattern = '[A-Za-z_$][\\w$]*(?:\\.[A-Za-z_$][\\w$]*)*';

    if (new RegExp(`^${stateAssignmentPattern}\\s*=\\s*\\$event\\.name$`).test(trimmedExpression)) {
        return trimmedExpression.replace('$event.name', '$event');
    }

    if (new RegExp(`^${stateAssignmentPattern}\\(\\s*\\$event\\.name\\s*\\)$`).test(trimmedExpression)) {
        return trimmedExpression.replace('$event.name', '$event');
    }

    return null;
}

function getClickAssignment(context, attr) {
    if (!isEventAttribute(attr) || getEventName(attr) !== 'click' || attr.key?.modifiers?.length > 0) {
        return null;
    }

    const expressionSource = getExpressionSource(context, attr)?.trim();
    const assignment = expressionSource?.match(/^([A-Za-z_$][\w$]*(?:\.[A-Za-z_$][\w$]*)*)\s*=\s*(['"])([^'"]+)\2$/);

    if (!assignment) {
        return null;
    }

    return {
        stateExpression: assignment[1],
        value: assignment[3],
    };
}

function activeExpressionMatchesState(context, attr, stateExpression, value) {
    const expressionSource = getExpressionSource(context, attr)?.trim();
    const statePattern = escapeRegExp(stateExpression);
    const valuePattern = escapeRegExp(value);

    return new RegExp(`^${statePattern}\\s*={2,3}\\s*(['"])${valuePattern}\\1$`).test(expressionSource ?? '');
}

function analyzeLocalStateTabs(context, node, tabItemChildren) {
    const attributes = node.startTag.attributes;

    if (
        tabItemChildren.length === 0 ||
        getAttribute(attributes, ['defaultitem']) ||
        getAttribute(attributes, [
            'active',
            'activetab',
        ]) ||
        attributes.some((attr) => isEventAttribute(attr) && getEventName(attr) === 'newitemactive')
    ) {
        return null;
    }

    const itemNames = new Map();
    let stateExpression = null;

    for (const item of tabItemChildren) {
        const itemAttributes = item.startTag.attributes;
        const eventAttributes = itemAttributes.filter(isEventAttribute);
        const clickAttribute = eventAttributes.find((attr) => getEventName(attr) === 'click');
        const activeAttribute = getAttribute(itemAttributes, ['active']);
        const assignment = getClickAssignment(context, clickAttribute);

        if (
            eventAttributes.length !== 1 ||
            !assignment ||
            !activeAttribute ||
            !activeExpressionMatchesState(context, activeAttribute, assignment.stateExpression, assignment.value)
        ) {
            return null;
        }

        if (stateExpression !== null && stateExpression !== assignment.stateExpression) {
            return null;
        }

        const nameAttribute = getAttribute(itemAttributes, ['name']);

        if (nameAttribute && getExpressionSource(context, nameAttribute) !== stringLiteral(assignment.value)) {
            return null;
        }

        stateExpression = assignment.stateExpression;
        itemNames.set(item, stringLiteral(assignment.value));
    }

    return {
        itemNames,
        stateExpression,
    };
}

function isAllowedDefaultSlotActiveAttribute(context, attr, options) {
    return Boolean(
        options.allowedActiveExpression &&
        hasName(attr, [
            'active',
            'activetab',
        ]) &&
        getExpressionSource(context, attr)?.trim() === options.allowedActiveExpression,
    );
}

function isAllowedLocalStateAttribute(attr, options) {
    return Boolean(
        options.localStateTabs?.itemNames.has(options.currentItem) &&
        (
            (isEventAttribute(attr) && getEventName(attr) === 'click') ||
            hasName(attr, ['active'])
        ),
    );
}

function findWrapperManualReasons(context, node, options = {}) {
    const attributes = node.startTag.attributes;
    const reasons = [];

    if (hasAncestor(node, manualWrapperComponents)) {
        reasons.push('wrapper component tab integrations need manual migration');
    }

    const hasContentSlot = node.children.some((child) => isSlotTemplate(child, 'content'));

    if (hasContentSlot) {
        reasons.push('content slots need manual active-tab state migration');
    }

    if (node.children.some((child) => (
        child.type === 'VElement' &&
        child.name === 'template' &&
        child !== options.supportedDefaultSlotTemplate &&
        !isSlotTemplate(child, 'content')
    ))) {
        reasons.push('template slot children need manual migration');
    }

    if (attributes.some((attr) => (
        isEventAttribute(attr) &&
        getEventName(attr) === 'newitemactive' &&
        !options.transformNewItemActiveHandlers
    ))) {
        reasons.push('old "new-item-active" handlers need manual payload migration');
    }

    if (options.transformNewItemActiveHandlers && attributes.some((attr) => (
        isEventAttribute(attr) &&
        getEventName(attr) === 'newitemactive' &&
        !getMigratedNewItemActiveHandler(context, attr)
    ))) {
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
            return false;
        }

        if (isDirective(attr, 'if') || isDirective(attr, 'show') || isDirective(attr, 'else') || isDirective(attr, 'else-if')) {
            return false;
        }

        if (hasName(attr, [
            'active',
            'activetab',
        ])) {
            return false;
        }

        const name = normalizeName(attributeName(attr));

        if ([
            'positionidentifier',
            'defaultitem',
            'isvertical',
            'alignright',
            'small',
            ...(options.allowIsSmall ? ['issmall'] : []),
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

function findFeatureFlagWrapperManualReasons(node) {
    const attributes = node.startTag.attributes;
    const reasons = [];

    if (attributes.some((attr) => isDirective(attr, 'else') || isDirective(attr, 'else-if'))) {
        reasons.push('existing conditional branches need manual feature-flag migration');
    }

    if (getAttribute(attributes, ['items'])) {
        reasons.push('existing "items" props need manual feature-flag migration');
    }

    return reasons;
}

function findItemManualReason(context, item, options = {}) {
    const attributes = item.startTag.attributes;
    const hasLocalState = options.localStateTabs?.itemNames.has(item) ?? false;

    if (attributes.some((attr) => isDirective(attr, 'for'))) {
        return 'dynamic "v-for" tab items need manual item builders';
    }

    if (attributes.some((attr) => hasName(attr, [
        'route',
    ]))) {
        return 'route tabs need manual onClick migration';
    }

    if (attributes.some((attr) => isEventAttribute(attr) && (!hasLocalState || !isAllowedLocalStateAttribute(attr, {
        ...options,
        currentItem: item,
    })))) {
        return 'custom tab item listeners need manual migration';
    }

    if (attributes.some((attr) => (
        hasName(attr, [
            'active',
            'activetab',
        ]) &&
        !isAllowedDefaultSlotActiveAttribute(context, attr, options) &&
        !isAllowedLocalStateAttribute(attr, {
            ...options,
            currentItem: item,
        })
    ))) {
        return 'old active tab item props need manual state migration';
    }

    if (attributes.some((attr) => hasName(attr, [
        'haswarning',
    ]))) {
        return '"has-warning" needs manual badge mapping';
    }

    const unsupportedAttribute = attributes.find((attr) => {
        if (isDirective(attr, 'for')) {
            return true;
        }

        if (isEventAttribute(attr)) {
            return !isAllowedLocalStateAttribute(attr, {
                ...options,
                currentItem: item,
            });
        }

        if (
            isAllowedDefaultSlotActiveAttribute(context, attr, options) ||
            isAllowedLocalStateAttribute(attr, {
                ...options,
                currentItem: item,
            })
        ) {
            return false;
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

    if (!getAttribute(attributes, ['name']) && !hasLocalState) {
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

function itemEntryFromNode(context, item, options = {}) {
    const attributes = item.startTag.attributes;
    const manualReason = findItemManualReason(context, item, options);

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

    const name = getExpressionSource(context, getAttribute(attributes, ['name'])) ?? options.localStateTabs?.itemNames.get(item);

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

function buildWrapperAttribute(context, attr, options = {}) {
    if (
        options.transformNewItemActiveHandlers &&
        isEventAttribute(attr) &&
        getEventName(attr) === 'newitemactive'
    ) {
        const migratedHandler = getMigratedNewItemActiveHandler(context, attr);

        if (migratedHandler) {
            return `@new-item-active="${escapeAttributeValue(migratedHandler)}"`;
        }
    }

    if (hasName(attr, ['isvertical'])) {
        if (attr.directive === true) {
            return `:vertical="${escapeAttributeValue(getExpressionSource(context, attr))}"`;
        }

        return attr.value ? `vertical=${sourceOf(context, attr.value)}` : 'vertical';
    }

    if (options.allowIsSmall && hasName(attr, ['issmall'])) {
        if (attr.directive === true) {
            return `:small="${escapeAttributeValue(getExpressionSource(context, attr))}"`;
        }

        return attr.value ? `small=${sourceOf(context, attr.value)}` : 'small';
    }

    return sourceOf(context, attr);
}

function buildReplacement(context, node, itemEntries, options = {}) {
    const indent = options.indent ?? ' '.repeat(node.loc.start.column);
    const includeFirstLineIndent = options.includeFirstLineIndent ?? false;
    const attributes = node.startTag.attributes;
    const hasItemsAttribute = Boolean(getAttribute(attributes, ['items']));
    const todoComments = [];
    const wrapperAttributes = [];

    attributes.forEach((attr) => {
        if (hasName(attr, ['alignright'])) {
            todoComments.push(alignRightTodo);
            return;
        }

        if (hasName(attr, ['small']) || (options.allowIsSmall && hasName(attr, ['issmall']))) {
            todoComments.push(smallTodo);
        }

        wrapperAttributes.push(buildWrapperAttribute(context, attr, options));
    });

    if (options.localStateTabs) {
        wrapperAttributes.push(`:default-item="${escapeAttributeValue(options.localStateTabs.stateExpression)}"`);
        wrapperAttributes.push(`@new-item-active="${escapeAttributeValue(`${options.localStateTabs.stateExpression} = $event`)}"`);
    }

    const lines = [];
    const pushRootLine = (line) => {
        lines.push(`${includeFirstLineIndent || lines.length > 0 ? indent : ''}${line}`);
    };

    [...new Set(todoComments)].forEach((comment) => {
        pushRootLine(`<!-- ${comment} -->`);
    });

    pushRootLine('<mt-tabs');
    wrapperAttributes.forEach((attrSource) => {
        lines.push(`${indent}    ${attrSource}`);
    });

    if (!hasItemsAttribute) {
        lines.push(`${indent}    :items="${escapeAttributeValue(buildItems(itemEntries, indent))}"`);
    }

    lines.push(`${indent}/>`);

    return lines.join('\n');
}

function indentOriginalBranch(context, node, indent) {
    const firstLineIndent = `${indent}    `;

    return sourceOf(context, node)
        .split('\n')
        .map((line, index) => `${index === 0 ? firstLineIndent : '    '}${line}`)
        .join('\n');
}

function buildFeatureFlagReplacement(context, node, itemEntries, featureFlag, options = {}) {
    const indent = ' '.repeat(node.loc.start.column);
    const branchIndent = `${indent}    `;
    const mtTabsBranch = buildReplacement(context, node, itemEntries, {
        ...options,
        indent: branchIndent,
        includeFirstLineIndent: true,
    });

    return [
        `<template v-if="feature.isActive('${featureFlag}')">`,
        mtTabsBranch,
        `${indent}</template>`,
        `${indent}<template v-else>`,
        indentOriginalBranch(context, node, indent),
        `${indent}</template>`,
    ].join('\n');
}

/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleSwTabs = (context, node, options = {}) => {
    const componentName = 'sw-tabs';
    const mode = options.mode ?? 'direct';

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
    const directTabItemChildren = meaningfulChildren.filter((child) => {
        return child.type === 'VElement' && child.name === 'sw-tabs-item';
    });
    const defaultSlotInfo = mode === 'feature-flag' ? getDefaultSlotInfo(context, node) : null;
    const defaultSlotTabItemChildren = defaultSlotInfo?.supported ? defaultSlotInfo.tabItemChildren : [];
    const tabItemChildren = [
        ...directTabItemChildren,
        ...defaultSlotTabItemChildren,
    ];
    const replacementOptions = {
        allowIsSmall: mode === 'feature-flag',
        supportedDefaultSlotTemplate: defaultSlotInfo?.supported ? defaultSlotInfo.template : null,
        transformNewItemActiveHandlers: mode === 'feature-flag',
    };
    const localStateTabs = mode === 'feature-flag' ? analyzeLocalStateTabs(context, node, tabItemChildren) : null;
    const wrapperManualReasons = findWrapperManualReasons(context, node, replacementOptions);

    if (mode === 'feature-flag') {
        wrapperManualReasons.push(...findFeatureFlagWrapperManualReasons(node));
    }

    if (directTabItemChildren.length > 0 && defaultSlotTabItemChildren.length > 0) {
        wrapperManualReasons.push('mixed direct and default-slot tab items need manual migration');
    }

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

    const itemEntries = tabItemChildren.map((item) => {
        return itemEntryFromNode(context, item, {
            allowedActiveExpression: defaultSlotTabItemChildren.includes(item) ? defaultSlotInfo?.activeVariable : null,
            localStateTabs,
        });
    });
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

            if (mode === 'feature-flag') {
                yield fixer.replaceText(
                    node,
                    buildFeatureFlagReplacement(
                        context,
                        node,
                        itemEntries,
                        options.featureFlag ?? 'v6.8.0.0',
                        {
                            ...replacementOptions,
                            localStateTabs,
                        },
                    ),
                );

                return;
            }

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
        name: '"sw-tabs" static item usage is migrated to feature-flagged "mt-tabs" items',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
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
    <template v-if="feature.isActive('v6.8.0.0')">
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
    </template>
    <template v-else>
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
    </template>
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" direct migration reports without fixing when disableFix is set',
        filename: 'test.html.twig',
        options: ['disableFix', 'migrateSwTabs'],
        code: `
<template>
    <sw-tabs>
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" feature-flag migration reports without fixing when disableFix is set',
        filename: 'test.html.twig',
        options: ['disableFix', 'migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs>
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" feature-flag migration extracts default-slot tab items',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs position-identifier="example-tabs">
        <template #default="{ active }">
            <sw-tabs-item
                name="general"
                :active-tab="active"
            >
                General
            </sw-tabs-item>
            <sw-tabs-item
                name="advanced"
                :active-tab="active"
                :disabled="isDisabled"
            >
                {{ $t('example.advanced') }}
            </sw-tabs-item>
        </template>
    </sw-tabs>
</template>`,
        output: `
<template>
    <template v-if="feature.isActive('v6.8.0.0')">
        <mt-tabs
            position-identifier="example-tabs"
            :items="[
                {
                    label: 'General',
                    name: 'general',
                },
                {
                    label: $t('example.advanced'),
                    name: 'advanced',
                    disabled: isDisabled,
                },
            ]"
        />
    </template>
    <template v-else>
        <sw-tabs position-identifier="example-tabs">
            <template #default="{ active }">
                <sw-tabs-item
                    name="general"
                    :active-tab="active"
                >
                    General
                </sw-tabs-item>
                <sw-tabs-item
                    name="advanced"
                    :active-tab="active"
                    :disabled="isDisabled"
                >
                    {{ $t('example.advanced') }}
                </sw-tabs-item>
            </template>
        </sw-tabs>
    </template>
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" feature-flag migration keeps wrapper v-if on both branches',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs
        v-if="showTabs"
        position-identifier="example-tabs"
    >
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        output: `
<template>
    <template v-if="feature.isActive('v6.8.0.0')">
        <mt-tabs
            v-if="showTabs"
            position-identifier="example-tabs"
            :items="[
                {
                    label: 'General',
                    name: 'general',
                },
            ]"
        />
    </template>
    <template v-else>
        <sw-tabs
            v-if="showTabs"
            position-identifier="example-tabs"
        >
            <sw-tabs-item name="general">General</sw-tabs-item>
        </sw-tabs>
    </template>
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" feature-flag migration keeps wrapper v-show on both branches',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs
        v-show="showTabs"
        position-identifier="example-tabs"
    >
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        output: `
<template>
    <template v-if="feature.isActive('v6.8.0.0')">
        <mt-tabs
            v-show="showTabs"
            position-identifier="example-tabs"
            :items="[
                {
                    label: 'General',
                    name: 'general',
                },
            ]"
        />
    </template>
    <template v-else>
        <sw-tabs
            v-show="showTabs"
            position-identifier="example-tabs"
        >
            <sw-tabs-item name="general">General</sw-tabs-item>
        </sw-tabs>
    </template>
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
        name: '"sw-tabs" feature-flag migration keeps small and align-right TODOs in the "mt-tabs" branch',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs small align-right>
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        output: `
<template>
    <template v-if="feature.isActive('v6.8.0.0')">
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
    </template>
    <template v-else>
        <sw-tabs small align-right>
            <sw-tabs-item name="general">General</sw-tabs-item>
        </sw-tabs>
    </template>
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" feature-flag migration maps is-small aliases in the "mt-tabs" branch',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs
        :is-small="false"
        position-identifier="example-tabs"
    >
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        output: `
<template>
    <template v-if="feature.isActive('v6.8.0.0')">
        <!-- TODO Codemod: The "small" prop is deprecated on mt-tabs. Check the surrounding layout after this migration. -->
        <mt-tabs
            :small="false"
            position-identifier="example-tabs"
            :items="[
                {
                    label: 'General',
                    name: 'general',
                },
            ]"
        />
    </template>
    <template v-else>
        <sw-tabs
            :is-small="false"
            position-identifier="example-tabs"
        >
            <sw-tabs-item name="general">General</sw-tabs-item>
        </sw-tabs>
    </template>
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
        name: '"sw-tabs" existing items props are manual-only in feature-flag mode',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs
        position-identifier="example-tabs"
        :items="tabs"
    />
</template>`,
        errors: [{
            message: '[sw-tabs] Cannot automatically migrate to "mt-tabs": existing "items" props need manual feature-flag migration.',
        }],
    },
    {
        name: '"sw-tabs" v-else-if conditionals are manual-only in feature-flag mode',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs v-else-if="showTabs">
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        errors: [{
            message: '[sw-tabs] Cannot automatically migrate to "mt-tabs": existing conditional branches need manual feature-flag migration.',
        }],
    },
    {
        name: '"sw-tabs" wrapper v-for is manual-only in feature-flag mode',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs v-for="tabGroup in tabGroups" :key="tabGroup.name">
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        errors: [{
            message: '[sw-tabs] Cannot automatically migrate to "mt-tabs": dynamic tab lists need manual item builders.',
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
        name: '"sw-tabs" route tabs are manual-only in feature-flag mode',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
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
        name: '"sw-tabs" content slots are manual-only in feature-flag mode',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
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
        name: '"sw-tabs" dynamic item lists are manual-only in feature-flag mode',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
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
        name: '"sw-tabs" new-item-active listeners are manual-only in feature-flag mode',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
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
        name: '"sw-tabs" feature-flag migration rewrites simple assignment new-item-active handlers',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs
        position-identifier="example-tabs"
        @new-item-active="activeTab = $event.name"
    >
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        output: `
<template>
    <template v-if="feature.isActive('v6.8.0.0')">
        <mt-tabs
            position-identifier="example-tabs"
            @new-item-active="activeTab = $event"
            :items="[
                {
                    label: 'General',
                    name: 'general',
                },
            ]"
        />
    </template>
    <template v-else>
        <sw-tabs
            position-identifier="example-tabs"
            @new-item-active="activeTab = $event.name"
        >
            <sw-tabs-item name="general">General</sw-tabs-item>
        </sw-tabs>
    </template>
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" feature-flag migration rewrites simple method new-item-active handlers',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs
        position-identifier="example-tabs"
        @new-item-active="setActiveTab($event.name)"
    >
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        output: `
<template>
    <template v-if="feature.isActive('v6.8.0.0')">
        <mt-tabs
            position-identifier="example-tabs"
            @new-item-active="setActiveTab($event)"
            :items="[
                {
                    label: 'General',
                    name: 'general',
                },
            ]"
        />
    </template>
    <template v-else>
        <sw-tabs
            position-identifier="example-tabs"
            @new-item-active="setActiveTab($event.name)"
        >
            <sw-tabs-item name="general">General</sw-tabs-item>
        </sw-tabs>
    </template>
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" custom item listeners are manual-only in feature-flag mode',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs position-identifier="example-tabs">
        <sw-tabs-item
            name="general"
            @click="onClick"
        >
            General
        </sw-tabs-item>
    </sw-tabs>
</template>`,
        errors: [{
            message: '[sw-tabs] Cannot automatically migrate to "mt-tabs": custom tab item listeners need manual migration.',
        }],
    },
    {
        name: '"sw-tabs" active props are manual-only in feature-flag mode',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs
        position-identifier="example-tabs"
        active-tab="general"
    >
        <sw-tabs-item name="general">General</sw-tabs-item>
    </sw-tabs>
</template>`,
        errors: [{
            message: '[sw-tabs] Cannot automatically migrate to "mt-tabs": old active tab props need manual state migration.',
        }],
    },
    {
        name: '"sw-tabs" feature-flag migration converts simple local state active and click pairs',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs position-identifier="example-tabs">
        <sw-tabs-item
            :active="activeTab == 'order'"
            @click="activeTab = 'order'"
        >
            Order
        </sw-tabs-item>
        <sw-tabs-item
            :active="activeTab === 'delivery'"
            @click="activeTab = 'delivery'"
        >
            Delivery
        </sw-tabs-item>
    </sw-tabs>
</template>`,
        output: `
<template>
    <template v-if="feature.isActive('v6.8.0.0')">
        <mt-tabs
            position-identifier="example-tabs"
            :default-item="activeTab"
            @new-item-active="activeTab = $event"
            :items="[
                {
                    label: 'Order',
                    name: 'order',
                },
                {
                    label: 'Delivery',
                    name: 'delivery',
                },
            ]"
        />
    </template>
    <template v-else>
        <sw-tabs position-identifier="example-tabs">
            <sw-tabs-item
                :active="activeTab == 'order'"
                @click="activeTab = 'order'"
            >
                Order
            </sw-tabs-item>
            <sw-tabs-item
                :active="activeTab === 'delivery'"
                @click="activeTab = 'delivery'"
            >
                Delivery
            </sw-tabs-item>
        </sw-tabs>
    </template>
</template>`,
        errors: [{
            message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.',
        }],
    },
    {
        name: '"sw-tabs" mixed local state variables are manual-only in feature-flag mode',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
        code: `
<template>
    <sw-tabs position-identifier="example-tabs">
        <sw-tabs-item
            :active="activeTab == 'order'"
            @click="activeTab = 'order'"
        >
            Order
        </sw-tabs-item>
        <sw-tabs-item
            :active="secondaryTab === 'delivery'"
            @click="secondaryTab = 'delivery'"
        >
            Delivery
        </sw-tabs-item>
    </sw-tabs>
</template>`,
        errors: [{
            message: '[sw-tabs] Cannot automatically migrate to "mt-tabs": custom tab item listeners need manual migration.',
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
    {
        name: '"sw-tabs" has-warning items are manual-only in feature-flag mode',
        filename: 'test.html.twig',
        options: ['migrateSwTabsFeatureFlag'],
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
