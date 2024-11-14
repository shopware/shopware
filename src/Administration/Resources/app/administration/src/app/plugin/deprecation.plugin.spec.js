/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import deprecationPlugin from 'src/app/plugin/deprecation.plugin';

const createComponent = ({ customComponent, customOptions, customGlobalOptions } = {}) => {
    const baseComponent = {
        name: 'base-component',
        template: '<div></div>',
        ...customComponent,
    };

    return mount(baseComponent, {
        ...{
            global: {
                plugins: [deprecationPlugin],
                ...customGlobalOptions,
            },
        },
        ...customOptions,
    });
};

describe('app/plugins/deprecated.plugin', () => {
    let component;
    let orgMock;

    beforeAll(() => {
        orgMock = global.console.warn;
    });

    beforeEach(async () => {
        global.console.warn = jest.fn();
    });

    afterEach(async () => {
        global.console.warn.mockReset();
        global.console.warn = orgMock;
        deprecationPlugin.pluginInstalled = false;

        await component.unmount();
        await flushPromises();
    });

    it('should test with a Vue.js component', async () => {
        component = createComponent();

        expect(component.vm).toBeTruthy();
    });

    it('should not throw an error if the example component gets created', async () => {
        component = createComponent();

        expect(global.console.warn).not.toHaveBeenCalled();
    });

    it('[prop] should not throw an error if the deprecated prop is not used', async () => {
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

        expect(global.console.warn).not.toHaveBeenCalled();
    });

    it('[prop] should throw an error if the deprecated (string) prop is used', async () => {
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

        expect(global.console.warn).toHaveBeenCalled();
    });

    it('[prop] should throw an error if the deprecated (object) prop is used', async () => {
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

        expect(global.console.warn).toHaveBeenCalled();
    });

    it('[prop] should show the relevant deprecation (string) information in the warning', async () => {
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

        // Revert to first call once compat warnings are fixed
        const lastCall = global.console.warn.mock.calls[0];

        expect(lastCall[0]).toEqual(expect.stringContaining('[base-component]'));
        expect(lastCall[1]).toEqual(expect.stringContaining('base-component'));
        expect(lastCall[1]).toEqual(expect.stringContaining('examplePropertyTest'));
        expect(lastCall[1]).toEqual(expect.stringContaining('6.4.0'));
    });

    it('[prop] should show the relevant deprecation (object) information in the warning', async () => {
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

        const firstCall = global.console.warn.mock.calls[0];

        expect(firstCall[0]).toEqual(expect.stringContaining('[base-component]'));
        expect(firstCall[1]).toEqual(expect.stringContaining('base-component'));
        expect(firstCall[1]).toEqual(expect.stringContaining('examplePropertyTest'));
        expect(firstCall[1]).toEqual(expect.stringContaining('6.4.0'));
    });

    it('[prop] should throw a trace after the warning', async () => {
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

        const secondCall = global.console.warn.mock.calls[1];

        expect(secondCall).toContain('[base-component]');
        expect(secondCall[1]).toEqual(expect.stringContaining('--> base-component'));
    });

    it('[prop] should show the additional comment in the warnings', async () => {
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

        const firstCall = global.console.warn.mock.calls[0];

        expect(firstCall[1]).toEqual(expect.stringContaining('Dale a tu cuerpo alegria, Macarena. \n Hey Macarena'));
    });

    it('[component] should throw a deprecation warning if the deprecated (string) component is used', async () => {
        component = createComponent({
            customComponent: {
                deprecated: '6.4.0',
            },
        });

        const firstCall = global.console.warn.mock.calls[0];

        expect(firstCall[0]).toEqual(expect.stringContaining('base-component'));
        expect(firstCall[1]).toEqual(expect.stringContaining('base-component'));
        expect(firstCall[1]).toEqual(expect.stringContaining('6.4.0'));
    });

    it('[component] should throw a deprecation warning if the deprecated (object) component is used', async () => {
        component = createComponent({
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
        component = createComponent({
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

    it('[component] should throw a trace after the warning', async () => {
        component = createComponent({
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

    it('[component] should throw a trace after the warning in nested components', async () => {
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

        // Check if any of the warnings contains the correct values
        let wasFound = false;
        global.console.warn.mock.calls.forEach((call) => {
            if (call[1].includes('base-component')) {
                wasFound = true;
            } else {
                return;
            }

            expect(call).toContain('[deprecated-component]');
            expect(call[1]).toEqual(expect.stringContaining('--> deprecated-component'));
            expect(call[1]).toEqual(expect.stringContaining('base-component'));
            expect(call[1]).toMatch(' --> deprecated-component \n      base-component ');
        });

        expect(wasFound).toBeTruthy();
    });
});
