import { mount } from '@vue/test-utils';
import { MtIcon } from '@shopware-ag/meteor-component-library';
import SwSettingsUsageDataConsentCheckList from './index';

describe('module/sw-settings-usage-data/component/sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-consent-check-list', () => {
    it('has follows the design', async () => {
        const wrapper = await mount(SwSettingsUsageDataConsentCheckList);

        const entries = wrapper.findAll('li');

        expect(entries).toHaveLength(3);

        entries.forEach((listEntry) => {
            const icon = listEntry.getComponent(MtIcon);
            expect(icon.exists()).toBe(true);
            expect(icon.props('size')).toBe('var(--scale-size-16)');
            expect(icon.props('color')).toBe('var(--color-icon-positive-default)');
        });
    });
});
