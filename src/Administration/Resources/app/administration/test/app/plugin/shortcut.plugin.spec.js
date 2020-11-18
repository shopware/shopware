import { mount, createLocalVue } from '@vue/test-utils';
import shortcutPlugin from 'src/app/plugin/shortcut.plugin';

Shopware.Utils.debounce = function debounce(fn) {
    return function execFunction(...args) {
        fn.apply(this, args);
    };
};

const localVue = createLocalVue();
localVue.use(shortcutPlugin);

const createWrapper = (componentOverride) => {
    const baseComponent = {
        name: 'base-component',
        template: '<div></div>',
        ...componentOverride
    };

    return mount(baseComponent, {
        attachToDocument: true,
        mocks: {
            $device: { getSystemKey: () => 'CTRL' }
        },
        localVue
    });
};

describe('app/plugins/shortcut.plugin', () => {
    let wrapper;

    afterEach(() => {
        wrapper.destroy();
    });

    it('should test with a Vue.js component', async () => {
        wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('String: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: 'onSave'
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });
        await wrapper.trigger('keydown', {
            key: 'CTRL'
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with boolean active: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active: true,
                    method: 'onSave'
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with boolean active: should NOT call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active: false,
                    method: 'onSave'
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Object with function active: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return true;
                    },
                    method: 'onSave'
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with function active: should NOT call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return false;
                    },
                    method: 'onSave'
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Object with function active which access the vue instance: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return this.activeValue;
                    },
                    method: 'onSave'
                }
            },
            computed: {
                activeValue() {
                    return true;
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with function active which access the vue instance: should NOT call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return this.activeValue;
                    },
                    method: 'onSave'
                }
            },
            computed: {
                activeValue() {
                    return false;
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Object with function: function should be executed for each shortcut press', async () => {
        const onSaveMock = jest.fn();
        let shouldExecute = true;

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return shouldExecute;
                    },
                    method: 'onSave'
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        // shortcut should be executed
        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).toHaveBeenCalledWith();

        // change value dynamically
        onSaveMock.mockReset();
        shouldExecute = false;

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        // shortcut should not be executed
        expect(onSaveMock).not.toHaveBeenCalledWith();
    });
});
