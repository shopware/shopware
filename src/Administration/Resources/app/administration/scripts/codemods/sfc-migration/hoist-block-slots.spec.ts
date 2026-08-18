/**
 * @sw-package framework
 */

import { hoistBlockSlots } from './hoist-block-slots';

describe('scripts/codemods/sfc-migration/hoist-block-slots', () => {
    it('moves the block inside the named-slot template it wrapped', () => {
        const result = hoistBlockSlots('<sw-modal><sw-block name="a"><template #footer>X</template></sw-block></sw-modal>');

        expect(result.blockers).toEqual([]);
        expect(result.template).toBe(
            '<sw-modal><template #footer>\n<sw-block name="a">\nX\n</sw-block>\n</template></sw-modal>',
        );
    });

    // Nesting order is what an override chain resolves against, so it survives the inversion unchanged.
    it('keeps the order of every block the slot was hoisted through', () => {
        const result = hoistBlockSlots(
            '<sw-page><sw-block name="outer"><sw-block name="inner">' +
                '<template #actions>X</template></sw-block></sw-block></sw-page>',
        );

        expect(result.blockers).toEqual([]);
        expect(result.template).toContain('<template #actions>');
        expect(result.template.indexOf('name="outer"')).toBeLessThan(result.template.indexOf('name="inner"'));
        expect(result.template.indexOf('<template #actions>')).toBeLessThan(result.template.indexOf('name="outer"'));
    });

    it('carries the slot scope with the slot template', () => {
        const result = hoistBlockSlots(
            '<sw-data-grid><sw-block name="a"><template #column-name="{ item }">{{ item.name }}' +
                '</template></sw-block></sw-data-grid>',
        );

        expect(result.blockers).toEqual([]);
        expect(result.template).toContain('<template #column-name="{ item }">');
    });

    it('leaves a default slot alone', () => {
        const template = '<sw-modal><sw-block name="a"><template #default>X</template></sw-block></sw-modal>';

        expect(hoistBlockSlots(template)).toEqual({ template, blockers: [] });
    });

    // Splitting the block would need its name twice, and two blocks of one name render every override twice.
    it('refuses a block that wraps more than the slot', () => {
        const result = hoistBlockSlots(
            '<sw-modal><sw-block name="a"><p>kept</p><template #footer>X</template></sw-block></sw-modal>',
        );

        expect(result.blockers).toEqual([
            'named slot inside a twig block cannot be hoisted (the block wraps more than the slot)',
        ]);
    });

    it('refuses a dynamic slot name, which cannot be proven non-default', () => {
        const result = hoistBlockSlots(
            '<sw-data-grid><sw-block name="a"><template #[column]>X</template></sw-block></sw-data-grid>',
        );

        expect(result.blockers).toEqual(['named slot inside a twig block cannot be hoisted (dynamic slot name)']);
    });

    it('refuses a slot no component owns', () => {
        const result = hoistBlockSlots('<sw-block name="a"><template #footer>X</template></sw-block>');

        expect(result.blockers).toEqual([
            'named slot inside a twig block cannot be hoisted (no component owns the slot)',
        ]);
    });
});
