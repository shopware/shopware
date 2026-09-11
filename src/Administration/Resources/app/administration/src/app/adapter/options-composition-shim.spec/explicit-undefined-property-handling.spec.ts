/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-return */

import { _overridesMap } from 'src/app/adapter/composition-extension-system';

import { ref } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });
    describe('Explicit undefined property handling:', () => {
        it('should return undefined from local state when data explicitly sets a value to undefined', () => {
            const previousState = {
                selectedId: ref('previous-value'),
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { selectedId: undefined };
                },
                methods: {
                    getSelectedId() {
                        return this.selectedId;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;

            expect(result.getSelectedId()).toBeUndefined();
        });
    });
});
