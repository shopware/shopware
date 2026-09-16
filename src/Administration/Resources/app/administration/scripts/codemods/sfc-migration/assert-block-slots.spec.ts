/**
 * @sw-package framework
 */

import { compile } from '@vue/compiler-dom';
import { SLOT_IN_BLOCK, assertBlockSlots } from './assert-block-slots';

/**
 * Pairs the verdict with Vue's own opinion of the markup, so an accepted template cannot pass for
 * the wrong reason — a typo Vue rejects would otherwise look like "no named slot found".
 */
function inspect(template: string): { errors: string[]; blockers: string[] } {
    const errors: string[] = [];

    compile(template, { onError: (error) => errors.push(error.message) });

    return { errors, blockers: assertBlockSlots(template) };
}

const ACCEPTED = { errors: [], blockers: [] };

describe('scripts/codemods/sfc-migration/assert-block-slots', () => {
    describe('blocked: the named slot is a direct child of a converted block', () => {
        it.each([
            [
                'shorthand',
                '<sw-block name="a"><template #footer>x</template></sw-block>',
            ],
            [
                'v-slot longhand',
                '<sw-block name="a"><template v-slot:footer>x</template></sw-block>',
            ],
            [
                'on a non-template element',
                '<sw-block name="a"><mt-button v-slot:footer="x">{{ x }}</mt-button></sw-block>',
            ],
            [
                'dynamic slot argument',
                '<sw-block name="a"><template #[dynamicName]>x</template></sw-block>',
            ],
            [
                'nested converted block',
                '<sw-block name="a"><sw-block name="b"><template #footer>x</template></sw-block></sw-block>',
            ],
        ])('reports a %s named slot', (_label, template) => {
            expect(assertBlockSlots(template)).toEqual([SLOT_IN_BLOCK]);
        });

        it('reports one blocker per template, not one per swallowed slot', () => {
            const template =
                '<sw-block name="a">' +
                '<template #header>h</template>' +
                '<template #footer>f</template>' +
                '<template #actions>a</template>' +
                '</sw-block>';

            expect(assertBlockSlots(template)).toEqual([SLOT_IN_BLOCK]);
        });
    });

    describe('allowed', () => {
        it('accepts a named slot on a child component inside the block', () => {
            expect(inspect('<sw-block name="a"><sw-modal><template #footer>x</template></sw-modal></sw-block>')).toEqual(
                ACCEPTED,
            );
        });

        it('accepts a converted block nested inside a named slot', () => {
            expect(inspect('<sw-modal><template #footer><sw-block name="a">x</sw-block></template></sw-modal>')).toEqual(
                ACCEPTED,
            );
        });

        // The build transform rejects an authored default slot on <sw-block>; reporting it here too
        // would produce two report entries for one cause.
        it.each([
            '<sw-block name="a"><template #default>x</template></sw-block>',
            '<sw-block name="a"><template v-slot>x</template></sw-block>',
            '<sw-block name="a"><template v-slot="{ item }">{{ item }}</template></sw-block>',
        ])('leaves the default slot to the build transform: %s', (template) => {
            expect(assertBlockSlots(template)).toEqual([]);
        });

        it('ignores a block whose name is bound dynamically', () => {
            expect(inspect('<sw-block :name="dynamicName"><template #footer>x</template></sw-block>')).toEqual(ACCEPTED);
        });

        it('returns no blocker for markup Vue cannot parse', () => {
            expect(assertBlockSlots('<sw-block name="a"><template #footer>x</sw-block>')).toEqual([]);
        });
    });
});
