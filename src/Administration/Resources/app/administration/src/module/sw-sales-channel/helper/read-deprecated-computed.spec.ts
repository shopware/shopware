/**
 * @sw-package discovery
 */
import readDeprecatedComputed from './read-deprecated-computed';

describe('src/module/sw-sales-channel/helper/read-deprecated-computed', () => {
    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('runs a computed function with the component as this', () => {
        const vm = {
            label: 'Agentic Commerce',
            $options: {
                computed: {
                    title(this: { label: string }) {
                        return this.label;
                    },
                },
            },
        };

        expect(readDeprecatedComputed(vm, 'title')).toBe('Agentic Commerce');
    });

    it('runs the getter of a computed with a getter and a setter', () => {
        const vm = {
            label: 'Agentic Commerce',
            $options: {
                computed: {
                    title: {
                        get(this: { label: string }) {
                            return this.label;
                        },
                    },
                },
            },
        };

        expect(readDeprecatedComputed(vm, 'title')).toBe('Agentic Commerce');
    });

    it('runs the getter with the deprecations of 6.8 silenced', () => {
        const silence = jest
            .spyOn(Shopware.Feature, 'silence')
            .mockImplementation((_majorFlag, callback) => `silenced ${String(callback())}`);
        const vm = {
            $options: {
                computed: {
                    title: () => 'Agentic Commerce',
                },
            },
        };

        expect(readDeprecatedComputed(vm, 'title')).toBe('silenced Agentic Commerce');
        expect(silence).toHaveBeenCalledWith('V6_8_0_0', expect.any(Function));
    });
});
