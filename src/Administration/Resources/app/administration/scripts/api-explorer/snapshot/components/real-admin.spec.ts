/**
 * @sw-package framework
 */

import * as path from 'node:path';
import { createAnalysisContext } from '../analysis-context';
import type { ComponentEntry, ExtractionResult } from '../types';
import { extractComponents } from '.';

const ADMIN_ROOT = path.resolve(__dirname, '../../../..');

describe('extractComponents on the Administration', () => {
    let extracted: ExtractionResult<ComponentEntry[]>;

    const component = (name: string): ComponentEntry => {
        const entry = extracted.result.find((candidate) => candidate.name === name);

        if (!entry) {
            throw new Error(`Component ${name} was not extracted`);
        }

        return entry;
    };

    beforeAll(async () => {
        extracted = await extractComponents(createAnalysisContext(ADMIN_ROOT));
    }, 180_000);

    it('extracts roughly all registered components', () => {
        expect(extracted.result.length).toBeGreaterThan(900);
        expect(new Set(extracted.result.map(({ name }) => name)).size).toBe(extracted.result.length);
    });

    it('extracts the block tree and members of sw-product-detail', () => {
        const productDetail = component('sw-product-detail');

        expect(productDetail.blocks).toHaveLength(28);
        expect(productDetail.blocks.find(({ name }) => name === 'sw_product_detail_actions_duplicate')?.parent).toBe(
            'sw_product_detail_actions_save_context_menu_actions',
        );
        expect(productDetail.props.find(({ name }) => name === 'creationStates')?.deprecation?.version).toBe('v6.8.0');
        expect(
            productDetail.methods.find(({ name }) => name === 'onSaveFinished')?.parameters.map(({ name }) => name),
        ).toEqual(['response']);
        expect(productDetail.data.map(({ name }) => name)).toContain('cloning');
    });

    it('names the parent of extended components', () => {
        expect(component('sw-entity-listing')).toMatchObject({ registration: 'extend', extends: 'sw-data-grid' });
    });

    it('prints no absolute paths in type texts', () => {
        const typeTexts = extracted.result.flatMap((entry) => [
            ...entry.data.map(({ type }) => type),
            ...entry.computed.map(({ type }) => type),
            ...entry.methods.flatMap(({ returnType, parameters }) => [
                returnType,
                ...parameters.map(({ type }) => type),
            ]),
        ]);

        expect(typeTexts.filter((text) => text.includes(ADMIN_ROOT) || /import\(["']\//.test(text))).toEqual([]);
    });
});
