/**
 * @sw-package framework
 */
import { migrationsFromData, removeTemplateBlock, renameTemplateBlock } from './index';
import type { MigrationData, RemoveTemplateBlockStep, RenameTemplateBlockStep } from './index';

const builders = {
    'remove-template-block': removeTemplateBlock,
    'rename-template-block': renameTemplateBlock,
};

describe('src/app/deprecation-registry/definitions/helpers/from-data', () => {
    it('turns the UPGRADE anchors into references and every usage into the step of its type', () => {
        const data: MigrationData<RemoveTemplateBlockStep | RenameTemplateBlockStep>[] = [
            {
                id: 'template-block.a',
                deprecatedIn: '6.7.0',
                removedIn: '6.8.0',
                description: 'A',
                upgrade: ['UPGRADE-6.8.md#a'],
                usage: [
                    { type: 'rename-template-block', from: 'sw_a', to: 'sw_b', component: 'sw-a' },
                    { type: 'remove-template-block', from: 'sw_c', message: 'Move it.' },
                    { type: 'remove-template-block', from: 'sw_d', fix: 'unsafe-auto' },
                ],
            },
        ];

        expect(migrationsFromData(data, builders)).toEqual([
            {
                id: 'template-block.a',
                deprecatedIn: '6.7.0',
                removedIn: '6.8.0',
                description: 'A',
                references: [{ type: 'upgrade', target: 'UPGRADE-6.8.md#a' }],
                usage: [
                    { kind: 'rename-template-block', fix: 'auto', from: 'sw_a', to: 'sw_b', component: 'sw-a' },
                    { kind: 'remove-template-block', fix: 'manual', from: 'sw_c', message: 'Move it.' },
                    { kind: 'remove-template-block', fix: 'unsafe-auto', from: 'sw_d' },
                ],
            },
        ]);
    });
});
