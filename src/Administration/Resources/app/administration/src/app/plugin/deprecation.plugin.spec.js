/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import deprecationPlugin from 'src/app/plugin/deprecation.plugin';

const createComponent = ({ customComponent, customOptions, customGlobalOptions } = {}) => {
    const baseComponent = {
        name: 'base-component',
        template: '<div></div>',
        ...customComponent,
    };

    // The plugin is installed globally by the Jest setup, mirroring the running application.
    return mount(baseComponent, {
        ...{
            global: {
                ...customGlobalOptions,
            },
        },
        ...customOptions,
    });
};

describe('app/plugins/deprecated.plugin', () => {
    let component;
    let guard;

    beforeEach(() => {
        guard = jest.spyOn(Shopware.Feature, 'triggerDeprecationOrThrow').mockImplementation(() => {});
    });

    afterEach(async () => {
        guard.mockRestore();

        await component?.unmount();
        await flushPromises();
        component = undefined;
    });

    it('should not guard anything if the example component gets created', async () => {
        component = createComponent();

        expect(guard).not.toHaveBeenCalled();
    });

    it('should install on every app, because each test mount creates its own', async () => {
        expect(deprecationPlugin.install({ mixin: () => {} })).toBe(true);

        const app = { mixin: () => {} };
        expect(deprecationPlugin.install(app)).toBe(true);
        expect(deprecationPlugin.install(app)).toBe(false);
    });

    describe('toMajorFlag', () => {
        it.each([
            [
                '6.4.0',
                'V6_4_0_0',
            ],
            [
                'v6.8.0.0',
                'V6_8_0_0',
            ],
            [
                '6.8',
                'V6_8_0_0',
            ],
        ])('turns the version %s into the feature flag %s', (version, flag) => {
            expect(deprecationPlugin.constructor.toMajorFlag(version)).toBe(flag);
        });
    });

    it('[prop] should not guard if the deprecated prop is not used', async () => {
        component = createComponent({
            customComponent: {
                props: {
                    example: {
                        type: String,
                        required: false,
                        deprecated: '6.4.0',
                        default: 'Lorem ipsum',
                    },
                },
            },
        });

        expect(guard).not.toHaveBeenCalled();
    });

    it('[prop] should guard if the deprecated (string) prop is used', async () => {
        component = createComponent({
            customComponent: {
                props: {
                    examplePropertyTest: {
                        type: String,
                        required: false,
                        deprecated: '6.4.0',
                        default: 'Lorem ipsum',
                    },
                },
            },

            customOptions: {
                props: {
                    examplePropertyTest: 'Test',
                },
            },
        });

        expect(guard).toHaveBeenCalledWith('V6_4_0_0', expect.stringContaining('examplePropertyTest'));
    });

    it('[prop] should guard if the deprecated (object) prop is used', async () => {
        component = createComponent({
            customComponent: {
                props: {
                    examplePropertyTest: {
                        type: String,
                        required: false,
                        deprecated: {
                            version: '6.4.0',
                        },
                        default: 'Lorem ipsum',
                    },
                },
            },

            customOptions: {
                props: {
                    examplePropertyTest: 'Test',
                },
            },
        });

        expect(guard).toHaveBeenCalledWith('V6_4_0_0', expect.stringContaining('examplePropertyTest'));
    });

    it('[prop] should name the component, the property and the removal version', async () => {
        component = createComponent({
            customComponent: {
                props: {
                    examplePropertyTest: {
                        type: String,
                        required: false,
                        deprecated: '6.4.0',
                        default: 'Lorem ipsum',
                    },
                },
            },

            customOptions: {
                props: {
                    examplePropertyTest: 'Test',
                },
            },
        });

        const message = guard.mock.calls[0][1];

        expect(message).toEqual(expect.stringContaining('base-component'));
        expect(message).toEqual(expect.stringContaining('examplePropertyTest'));
        expect(message).toEqual(expect.stringContaining('6.4.0'));
    });

    it('[prop] should name the component, the property and the removal version in object notation', async () => {
        component = createComponent({
            customComponent: {
                props: {
                    examplePropertyTest: {
                        type: String,
                        required: false,
                        deprecated: {
                            version: '6.4.0',
                        },
                        default: 'Lorem ipsum',
                    },
                },
            },

            customOptions: {
                props: {
                    examplePropertyTest: 'Test',
                },
            },
        });

        const message = guard.mock.calls[0][1];

        expect(message).toEqual(expect.stringContaining('base-component'));
        expect(message).toEqual(expect.stringContaining('examplePropertyTest'));
        expect(message).toEqual(expect.stringContaining('6.4.0'));
    });

    it('[prop] should guard a deprecated prop supplied at its default value', async () => {
        component = createComponent({
            customComponent: {
                props: {
                    examplePropertyTest: {
                        type: String,
                        required: false,
                        deprecated: '6.4.0',
                        default: 'Lorem ipsum',
                    },
                },
            },

            customOptions: {
                props: {
                    examplePropertyTest: 'Lorem ipsum',
                },
            },
        });

        expect(guard).toHaveBeenCalledWith('V6_4_0_0', expect.stringContaining('examplePropertyTest'));
    });

    it('[prop] should guard a camelCase prop supplied with its kebab-case attribute name', async () => {
        component = createComponent({
            customComponent: {
                template: '<deprecated-component example-property-test="value" />',
            },

            customGlobalOptions: {
                stubs: {
                    'deprecated-component': {
                        name: 'deprecated-component',
                        template: '<div></div>',
                        props: {
                            examplePropertyTest: {
                                type: String,
                                required: false,
                                deprecated: '6.4.0',
                            },
                        },
                    },
                },
            },
        });

        expect(guard).toHaveBeenCalledWith('V6_4_0_0', expect.stringContaining('examplePropertyTest'));
    });

    it('[prop] should append the component trace, so two usage sites are reported separately', async () => {
        component = createComponent({
            customComponent: {
                props: {
                    example: {
                        type: String,
                        required: false,
                        deprecated: '6.4.0',
                        default: 'Lorem ipsum',
                    },
                },
            },

            customOptions: {
                props: {
                    example: 'Test',
                },
            },
        });

        expect(guard.mock.calls[0][1]).toEqual(expect.stringContaining('--> base-component'));
    });

    it('[prop] should show the additional comment', async () => {
        component = createComponent({
            customComponent: {
                props: {
                    examplePropertyTest: {
                        type: String,
                        required: false,
                        deprecated: {
                            version: '6.4.0',
                            comment: 'Dale a tu cuerpo alegria, Macarena. \n Hey Macarena',
                        },
                        default: 'Lorem ipsum',
                    },
                },
            },

            customOptions: {
                props: {
                    examplePropertyTest: 'Test',
                },
            },
        });

        expect(guard.mock.calls[0][1]).toEqual(
            expect.stringContaining('Dale a tu cuerpo alegria, Macarena. \n Hey Macarena'),
        );
    });

    it('[component] should guard a deprecated (string) component', async () => {
        component = createComponent({
            customComponent: {
                deprecated: '6.4.0',
            },
        });

        expect(guard).toHaveBeenCalledWith('V6_4_0_0', expect.stringContaining('base-component'));
        expect(guard.mock.calls[0][1]).toEqual(expect.stringContaining('6.4.0'));
    });

    it('[component] should guard a deprecated (object) component', async () => {
        component = createComponent({
            customComponent: {
                deprecated: {
                    version: '6.4.0',
                },
            },
        });

        expect(guard).toHaveBeenCalledWith('V6_4_0_0', expect.stringContaining('base-component'));
        expect(guard.mock.calls[0][1]).toEqual(expect.stringContaining('6.4.0'));
    });

    it('[component] should show the additional comment', async () => {
        component = createComponent({
            customComponent: {
                deprecated: {
                    version: '6.4.0',
                    comment: 'Summer of 69',
                },
            },
        });

        expect(guard.mock.calls[0][1]).toEqual(expect.stringContaining('Summer of 69'));
    });

    it('[component] should append the component trace', async () => {
        component = createComponent({
            customComponent: {
                deprecated: {
                    version: '6.4.0',
                    comment: 'Summer of 69',
                },
            },
        });

        expect(guard.mock.calls[0][1]).toEqual(expect.stringContaining('--> base-component'));
    });

    it('[component] should trace nested components', async () => {
        component = createComponent({
            customComponent: {
                template: `
                <div>
                    <deprecated-component></deprecated-component>
                </div>
                `,
            },

            customOptions: {},

            customGlobalOptions: {
                stubs: {
                    'deprecated-component': {
                        name: 'deprecated-component',
                        template: '<div></div>',
                        deprecated: '6.4.0',
                    },
                },
            },
        });

        const nestedCall = guard.mock.calls.find((call) => call[1].includes('deprecated-component'));

        expect(nestedCall).toBeDefined();
        expect(nestedCall[1]).toMatch(' --> deprecated-component \n      base-component ');
    });

    it('throws once the major flag of the deprecation is active', async () => {
        guard.mockRestore();
        guard = jest.spyOn(Shopware.Feature, 'isActive').mockReturnValue(true);

        expect(() => deprecationPlugin.guard('v6.8.0.0', 'The component "base-component" is deprecated')).toThrow(
            'Tried to access deprecated functionality: The component "base-component" is deprecated',
        );
    });
});
