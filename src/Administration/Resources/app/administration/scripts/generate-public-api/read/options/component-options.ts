/**
 * @sw-package framework
 *
 * Finds the options object of a registered component, the object literal that declares its members.
 */
import ts from 'typescript';
import type { Registration } from '../../model/model';
import type { SourceTree } from '../../source/source-tree';
import { valueOf } from '../script-nodes';

/** @private */
export interface ComponentOptions {
    object: ts.ObjectLiteralExpression;
    source: ts.SourceFile;
}

// Both return the object they get, they only tell the type checker that it is a component.
const TYPE_HELPER = /(?:^|\.)(?:wrapComponentConfig|defineComponent)$/;

function optionsIn(expression: ts.Expression, source: ts.SourceFile): ts.ObjectLiteralExpression | undefined {
    const value = valueOf(expression, source);

    if (ts.isObjectLiteralExpression(value)) {
        return value;
    }

    const isTypeHelper = ts.isCallExpression(value) && TYPE_HELPER.test(value.expression.getText(source));

    return isTypeHelper && value.arguments.length > 0 ? optionsIn(value.arguments[0], source) : undefined;
}

/** The object literal of `Component.register('sw-example', { ... })`, for a component without a file of its own. */
function inlineOptions(node: ts.Node, name: string): ts.ObjectLiteralExpression | undefined {
    const isRegistration =
        ts.isCallExpression(node) &&
        ts.isPropertyAccessExpression(node.expression) &&
        ['register', 'extend'].includes(node.expression.name.text) &&
        node.arguments.length > 0 &&
        ts.isStringLiteralLike(node.arguments[0]) &&
        node.arguments[0].text === name;

    return isRegistration
        ? node.arguments.find(ts.isObjectLiteralExpression)
        : ts.forEachChild(node, (child) => inlineOptions(child, name));
}

/**
 * @private
 *
 * The options object of the component, `undefined` when its source declares none that can be read: a native
 * component declares its members in `<script setup>`, and a source may build its options at runtime.
 */
export function componentOptions(
    { name, language, source: file }: Registration,
    tree: SourceTree,
): ComponentOptions | undefined {
    if (language === 'vue') {
        return undefined;
    }

    const source = ts.createSourceFile(file, tree.read(file), ts.ScriptTarget.Latest, true);
    const exported = source.statements.find(ts.isExportAssignment)?.expression;
    const object = exported === undefined ? inlineOptions(source, name) : optionsIn(exported, source);

    return object && { object, source };
}
