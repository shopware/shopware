/**
 * @package admin
 */

import { shallowMount, createLocalVue } from '@vue/test-utils';
import deprecationPlugin from 'src/app/plugin/deprecation.plugin';

const localVue = createLocalVue();
localVue.use(deprecationPlugin);

const createComponent = ({ customComponent, customOptions } = {}) => {
    const baseComponent = {
        name: 'base-component',
        template: '<div></div>',
        ...customComponent,
    };

    const options = { localVue, ...customOptions };

    return shallowMount(baseComponent, options);
};

describe('app/plugins/deprecated.plugin', () => {
    beforeEach(async () => {
        global.console.warn = jest.fn();
    });

    afterEach(() => {
        global.console.warn.mockReset();
    });

    it('should test with a Vue.js component', async () => {
        const component = createComponent();

        expect(component.vm).toBeTruthy();
    });

    it('should throw an warning if the plugin gets registered twice', async () => {
        const localVueSecond = createLocalVue();
        localVueSecond.use(deprecationPlugin);

        createComponent();

        expect(global.console.warn).toHaveBeenLastCalledWith('[Deprecation Plugin]', 'This plugin is already installed');
    });

    it('should not throw an error if the example component gets created', async () => {
        createComponent();

        expect(global.console.warn).not.toHaveBeenCalled();
    });

    it('[prop] should not throw an error if the deprecated prop is not used', async () => {
        createComponent({
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

        expect(global.console.warn).not.toHaveBeenCalled();
    });

    it('[prop] should throw an error if the deprecated (string) prop is used', async () => {
        createComponent({
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
                propsData: {
                    examplePropertyTest: 'Test',
                },
            },
        });

        expect(global.console.warn).toHaveBeenCalled();
    });

    it('[prop] should throw an error if the deprecated (object) prop is used', async () => {
        createComponent({
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
                propsData: {
                    examplePropertyTest: 'Test',
                },
            },
        });

        expect(global.console.warn).toHaveBeenCalled();
    });

    it('[prop] should show the relevant deprecation (string) information in the warning', async () => {
        createComponent({
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
                propsData: {
                    examplePropertyTest: 'Test',
                },
            },
        });

        const firstCall = global.console.warn.mock.calls[0];

        expect(firstCall[0]).toEqual(expect.stringContaining('[base-component]'));
        expect(firstCall[1]).toEqual(expect.stringContaining('base-component'));
        expect(firstCall[1]).toEqual(expect.stringContaining('examplePropertyTest'));
        expect(firstCall[1]).toEqual(expect.stringContaining('6.4.0'));
    });

    it('[prop] should show the relevant deprecation (object) information in the warning', async () => {
        createComponent({
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
                propsData: {
                    examplePropertyTest: 'Test',
                },
            },
        });

        const firstCall = global.console.warn.mock.calls[0];

        expect(firstCall[0]).toEqual(expect.stringContaining('[base-component]'));
        expect(firstCall[1]).toEqual(expect.stringContaining('base-component'));
        expect(firstCall[1]).toEqual(expect.stringContaining('examplePropertyTest'));
        expect(firstCall[1]).toEqual(expect.stringContaining('6.4.0'));
    });

    it('[prop] should throw an trace after the warning', async () => {
        createComponent({
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
                propsData: {
                    example: 'Test',
                },
            },
        });

        const secondCall = global.console.warn.mock.calls[1];

        expect(secondCall).toContain('[base-component]');
        expect(secondCall[1]).toEqual(expect.stringContaining('--> base-component'));
    });

    it('[prop] should show the additional comment in the warnings', async () => {
        createComponent({
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
                propsData: {
                    examplePropertyTest: 'Test',
                },
            },
        });

        const firstCall = global.console.warn.mock.calls[0];

        expect(firstCall[1]).toEqual(expect.stringContaining('Dale a tu cuerpo alegria, Macarena. \n Hey Macarena'));
    });

    it('[component] should throw an deprecation warning if the deprecated (string) component is used', async () => {
        createComponent({
            customComponent: {
                deprecated: '6.4.0',
            },
        });

        const firstCall = global.console.warn.mock.calls[0];

        expect(firstCall[0]).toEqual(expect.stringContaining('base-component'));
        expect(firstCall[1]).toEqual(expect.stringContaining('base-component'));
        expect(firstCall[1]).toEqual(expect.stringContaining('6.4.0'));
    });

    it('[component] should throw an deprecation warning if the deprecated (object) component is used', async () => {
        createComponent({
            customComponent: {
                deprecated: {
                    version: '6.4.0',
                },
            },
        });

        const firstCall = global.console.warn.mock.calls[0];

        expect(firstCall[0]).toEqual(expect.stringContaining('base-component'));
        expect(firstCall[1]).toEqual(expect.stringContaining('base-component'));
        expect(firstCall[1]).toEqual(expect.stringContaining('6.4.0'));
    });

    it('[component] should show the additional comment in the warnings', async () => {
        createComponent({
            customComponent: {
                deprecated: {
                    version: '6.4.0',
                    comment: 'Summer of 69',
                },
            },
        });

        const firstCall = global.console.warn.mock.calls[0];

        expect(firstCall[0]).toEqual(expect.stringContaining('base-component'));
        expect(firstCall[1]).toEqual(expect.stringContaining('base-component'));
        expect(firstCall[1]).toEqual(expect.stringContaining('6.4.0'));
        expect(firstCall[1]).toEqual(expect.stringContaining('Summer of 69'));
    });

    it('[component] should throw an trace after the warning', async () => {
        createComponent({
            customComponent: {
                deprecated: {
                    version: '6.4.0',
                    comment: 'Summer of 69',
                },
            },
        });

        const secondCall = global.console.warn.mock.calls[1];

        expect(secondCall).toContain('[base-component]');
        expect(secondCall[1]).toEqual(expect.stringContaining('--> base-component'));
    });

    it('[component] should throw an trace after the warning in nested components', async () => {
        createComponent({
            customComponent: {
                template: `
                <div>
                    <deprecated-component></deprecated-component>
                </div>
                `,
            },

            customOptions: {
                stubs: {
                    'deprecated-component': {
                        name: 'deprecated-component',
                        template: '<div></div>',
                        deprecated: '6.4.0',
                    },
                },
            },
        });

        const secondCall = global.console.warn.mock.calls[1];

        expect(secondCall).toContain('[deprecated-component]');
        expect(secondCall[1]).toEqual(expect.stringContaining('--> deprecated-component'));
        expect(secondCall[1]).toEqual(expect.stringContaining('base-component'));
        expect(secondCall[1]).toMatch(
            ' --> deprecated-component \n' +
            '      base-component ',
        );
    });
});
