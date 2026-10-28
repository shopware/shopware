/**
 * @sw-package framework
 */
import ComponentFactory from '../async-component.factory';
import type { ComponentConfig, SetupHmrMetadata } from '../async-component.factory';
import TemplateFactory from '../template.factory';

function nativeConfig(name: string) {
    const metadata: SetupHmrMetadata = {
        componentName: name,
        mode: 'base',
        version: '1',
        invalidate: jest.fn(),
        data: { reloadPage: false },
    };
    return { name, render: jest.fn(), __shopwareSetupHmr: metadata };
}

describe('native script HMR during component composition', () => {
    beforeEach(() => {
        ComponentFactory.getComponentRegistry().clear();
        ComponentFactory.getOverrideRegistry().clear();
        TemplateFactory.getTemplateRegistry().clear();
        TemplateFactory.getNormalizedTemplateRegistry().clear();
        ComponentFactory.markComponentTemplatesAsNotResolved();
    });

    it('allows a plain native definition to use its Vue HMR boundary', async () => {
        const config = nativeConfig('sw-native-plain');
        ComponentFactory.register(config.name, config);
        await ComponentFactory.build(config.name);
        expect(config.__shopwareSetupHmr.data?.reloadPage).toBe(false);
    });

    it('marks a composed native definition before its lazy override resolves', async () => {
        const config = nativeConfig('sw-native-pending');
        ComponentFactory.register(config.name, config);
        let complete: (value: ComponentConfig) => void = jest.fn();
        const pending = new Promise<ComponentConfig>((resolve) => {
            complete = resolve;
        });
        ComponentFactory.override(config.name, () => pending);
        const build = ComponentFactory.build(config.name);
        await flushPromises();
        expect(config.__shopwareSetupHmr.data?.reloadPage).toBe(true);
        complete({ render: jest.fn() });
        await expect(build).resolves.toHaveProperty('render');
    });

    it('marks a native parent when a legacy child inherits from it', async () => {
        const parent = nativeConfig('sw-native-shared-parent');
        ComponentFactory.register(parent.name, parent);
        ComponentFactory.extend('sw-native-legacy-child', parent.name, { render: jest.fn() });
        await ComponentFactory.build('sw-native-legacy-child');
        expect(parent.__shopwareSetupHmr.data?.reloadPage).toBe(true);
    });

    it('marks an inherited native definition for a full factory rebuild', async () => {
        const config = nativeConfig('sw-native-inherited');
        ComponentFactory.register('sw-native-parent', { render: jest.fn() });
        ComponentFactory.extend(config.name, 'sw-native-parent', config);
        await ComponentFactory.build(config.name);
        expect(config.__shopwareSetupHmr.data?.reloadPage).toBe(true);
    });
});
