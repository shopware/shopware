/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-argument */

import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import { shouldActivateShim, convertOptionsApiOverrideToCompositionApi } from 'src/app/adapter/options-composition-shim';

import { ref } from 'vue';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });
    describe('shouldActivateShim():', () => {
        it('should return true when override has methods', () => {
            const result = shouldActivateShim({
                methods: { save() {} },
            });

            expect(result).toBe(true);
        });

        it('should return true when override has computed', () => {
            const result = shouldActivateShim({
                computed: {
                    fullName() {
                        return '';
                    },
                },
            });

            expect(result).toBe(true);
        });

        it('should return true when override has data', () => {
            const result = shouldActivateShim({
                data() {
                    return { count: 0 };
                },
            });

            expect(result).toBe(true);
        });

        it('should return true when override has watch', () => {
            const result = shouldActivateShim({
                watch: { count() {} },
            });

            expect(result).toBe(true);
        });

        it('should return true when override has inject', () => {
            const result = shouldActivateShim({
                inject: ['repositoryFactory'],
            });

            expect(result).toBe(true);
        });

        it('should return true when override has mixins', () => {
            const result = shouldActivateShim({
                mixins: [{ methods: { foo() {} } }],
            });

            expect(result).toBe(true);
        });

        it('should return true when override has lifecycle hooks', () => {
            const result = shouldActivateShim({
                mounted() {},
            });

            expect(result).toBe(true);
        });

        it('should return true when mixin has lifecycle hooks', () => {
            const result = shouldActivateShim({
                mixins: [{ created() {} }],
            });

            expect(result).toBe(true);
        });

        it('should return false when override has no Options API patterns', () => {
            const result = shouldActivateShim({
                name: 'sw-example',
            });

            expect(result).toBe(false);
        });

        it('should return false for empty config', () => {
            const result = shouldActivateShim({});

            expect(result).toBe(false);
        });

        it('should return false for a normal template/setup component', () => {
            const result = shouldActivateShim({
                template: '<div>{{ count }}</div>',
                setup() {
                    return { count: ref(0) };
                },
            });

            expect(result).toBe(false);
        });

        it('should return false for an empty mixins array', () => {
            const result = shouldActivateShim({
                mixins: [],
            });

            expect(result).toBe(false);
        });

        it('should activate for extends without an unsupported warning', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const baseComponent = { methods: { foo() {} } };
            const result = shouldActivateShim({ extends: baseComponent } as any);

            expect(result).toBe(true);

            // Activating the shim path triggers checkUnsupportedFeatures, which warns about extends
            convertOptionsApiOverrideToCompositionApi('originalComponent', { extends: baseComponent } as any);

            const extendsWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('"extends" is not supported'),
            );
            expect(extendsWarnings).toHaveLength(0);

            consoleWarn.mockRestore();
        });
    });
});
