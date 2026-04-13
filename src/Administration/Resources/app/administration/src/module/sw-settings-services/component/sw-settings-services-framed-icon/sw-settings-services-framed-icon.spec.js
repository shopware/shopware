import { mount } from '@vue/test-utils';
import SwSettingsServicesFramedIcon from './index';

describe('src/module/sw-settings-services/component/sw-settings-services-framed-icon', () => {
    it('passes down props', async () => {
        const icon = await mount(SwSettingsServicesFramedIcon, {
            props: {
                size: '32px',
                imageUrl: 'https://shopware.com/something',
            },
        });
        await flushPromises();

        const baseElement = icon.get('.sw-settings-services-framed-icon');

        expect(baseElement.attributes('style')).toBe('font-size: 32px;');

        const image = icon.get('img');
        expect(image.attributes('src')).toBe('https://shopware.com/something');
    });
});
