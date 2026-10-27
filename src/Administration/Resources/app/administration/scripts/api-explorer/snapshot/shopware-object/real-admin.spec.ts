/**
 * @sw-package framework
 */

import * as path from 'node:path';
import { createAnalysisContext } from '../analysis-context';
import type { ExtractionResult, ShopwareObjectNode } from '../types';
import { extractShopwareObject } from '.';

const ADMIN_ROOT = path.resolve(__dirname, '..', '..', '..', '..');

function flatten(node: ShopwareObjectNode): ShopwareObjectNode[] {
    return [
        node,
        ...node.children.flatMap(flatten),
    ];
}

describe('extractShopwareObject on the real Administration', () => {
    let extraction: ExtractionResult<ShopwareObjectNode>;
    let nodes: Map<string, ShopwareObjectNode>;

    beforeAll(async () => {
        extraction = await extractShopwareObject(createAnalysisContext(ADMIN_ROOT));
        nodes = new Map(flatten(extraction.result).map((node) => [node.path, node]));
    }, 120_000);

    it('extracts a complete tree without diagnostics', () => {
        expect(extraction.diagnostics).toEqual([]);
        expect(nodes.size).toBeGreaterThanOrEqual(300);
        expect(nodes.size).toBeLessThanOrEqual(3000);
    });

    it('keeps the overloads of Component.register', () => {
        expect(nodes.get('Shopware.Component.register')?.callSignatures.length).toBeGreaterThanOrEqual(2);
    });

    it('reads JSDoc types of JS classes', () => {
        const generate = nodes.get('Shopware.Data.ChangesetGenerator#generate');

        expect(generate?.typeSource).toBe('jsdoc');
        expect(generate?.callSignatures[0].returnType).toContain('deletionQueue');
    });

    it('reads deprecation and visibility tags', () => {
        expect(nodes.get('Shopware.State')?.deprecation?.version).toBe('v6.8.0');
        expect(nodes.get('Shopware.Classes._private')?.visibility).toBe('private');
        expect(nodes.get('Shopware.Classes._private.HttpFactory')?.visibility).toBe('private');
    });

    it('ignores a @private on alias targets of public members', () => {
        expect(nodes.get('Shopware.InAppPurchase')?.visibility).toBe('public');
        expect(nodes.get('Shopware.Template.register')?.visibility).toBe('public');
        expect(nodes.get('Shopware.Component.build')?.visibility).toBe('public');
    });

    it('prints unions in a canonical order', () => {
        const responseType = nodes.get('Shopware.Data.Repository#httpClient.defaults.responseType')?.signature ?? '';
        const members = responseType.split(' | ');

        expect(members.length).toBeGreaterThan(2);
        expect(members).toEqual([...members].sort());
    });

    it('prints no absolute paths', () => {
        const texts = [...nodes.values()].flatMap((node) => [
            node.signature,
            ...node.callSignatures.flatMap((signature) => [
                signature.returnType,
                ...signature.parameters.map(({ type }) => type),
            ]),
        ]);

        expect(texts.filter((text) => text.includes(ADMIN_ROOT) || /import\("\//.test(text))).toEqual([]);
    });
});
