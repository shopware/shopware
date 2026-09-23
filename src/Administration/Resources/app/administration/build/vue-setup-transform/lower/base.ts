/**
 * @sw-package framework
 */

import type MagicString from 'magic-string';
import { RESERVED_BINDING_PREFIX } from '../naming';
import type { BaseSetupScriptAnalysis } from '../script-analyzer';
import type { ShopwareSetupBlock } from '../sfc-parser';
import type { TemplateAnalysis } from '../template-analyzer';
import { RUNTIME, formatObjectProperties, propertyKey, quote } from './shared';

const LATE = `${RESERVED_BINDING_PREFIX}Late`;

function toAuthorAlias(name: string): string {
    return `${RESERVED_BINDING_PREFIX}Author_${name}`;
}

function formatStateMap(names: string[]): string {
    return formatObjectProperties(
        names.map((name) => `${propertyKey(name)}: ${toAuthorAlias(name)}`),
        8,
    );
}

/**
 * Keeps the author body native. Every top-level runtime binding is renamed to its author alias, a footer
 * re-declares the original names from the override-aware state `attach()` returns, and references that
 * run after setup read that state through the late-binding object.
 */
function lowerBase(
    s: MagicString,
    block: ShopwareSetupBlock,
    analysis: BaseSetupScriptAnalysis,
    template: TemplateAnalysis,
): void {
    const offset = block.contentStart;
    const { marker } = analysis;
    const publicNames = new Set(analysis.publicEntries);
    const privateNames = analysis.runtimeBindings.filter((name) => !publicNames.has(name));
    const targets = analysis.renameTargets.filter((target) => target.start < marker.start || target.end > marker.end);
    const lateNames = analysis.runtimeBindings.filter((name) =>
        targets.some((target) => target.deferred && target.localName === name),
    );

    template.dataScopeInsertions.forEach((at) => s.appendLeft(at, ' :data="$dataScope"'));

    targets.forEach((target) => {
        const name = target.localName;
        const replacement = target.deferred ? `${LATE}.${name}` : toAuthorAlias(name);

        if (target.expansion === 'shorthand-property') {
            s.appendLeft(offset + target.start, `${name}: `);
        }

        s.overwrite(
            offset + target.start,
            offset + target.end,
            target.expansion === 'shorthand-export' ? `${replacement} as ${name}` : replacement,
            { storeName: true },
        );
    });

    s.remove(offset + marker.start, offset + marker.end);

    const header = [`const ${RUNTIME} = globalThis.Shopware.Component.__setupRuntime.v1;`];

    if (lateNames.length > 0) {
        header.push(
            `const ${LATE} = ${RUNTIME}.late(${formatObjectProperties(
                lateNames.map((name) => `${propertyKey(name)}: () => ${toAuthorAlias(name)}`),
                4,
            )});`,
        );
    }

    s.appendLeft(offset + analysis.bodyStart, `${header.join('\n')}\n`);

    const footer = [
        'const {',
        ...analysis.runtimeBindings.map((name) => `    ${name},`),
        `} = ${RUNTIME}.attach({`,
        `    name: ${quote(block.componentName)},`,
        `    public: ${formatStateMap(analysis.publicEntries)},`,
        `    private: ${formatStateMap(privateNames)},`,
        ...(lateNames.length > 0 ? [`    late: ${LATE},`] : []),
        '});',
        '',
        `defineExpose(${formatObjectProperties([`...${RUNTIME}.expose()`, ...analysis.publicEntries], 4)});`,
    ];

    s.appendRight(block.contentEnd, `\n\n${footer.join('\n')}\n`);
}

/**
 * @private
 */
export { lowerBase };
