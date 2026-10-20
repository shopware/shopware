/**
 * @sw-package checkout
 *
 * @module core/service/seller-country-listener
 */
import type { App } from 'vue';
import type { LoginService } from './login.service';

const SELLER_COUNTRY_CONFIG_KEY = 'core.basicInformation.sellerCountryId';

/**
 * Reminds the merchant on every login to set the shop owner's country, which falls back to the
 * sales channel's default country while it is empty.
 *
 * @private
 */
export default function addSellerCountryListener(loginService: LoginService): void {
    loginService.addOnLoginListener(() => {
        checkSellerCountry().catch(() => {
            /* the reminder must not break the login */
        });
    });

    async function checkSellerCountry(): Promise<void> {
        if (!Shopware.Service('acl').can('system.system_config')) {
            return;
        }

        const values = (await Shopware.Service('systemConfigApiService').getValues('core.basicInformation')) as Record<
            string,
            unknown
        >;

        if (values[SELLER_COUNTRY_CONFIG_KEY]) {
            return;
        }

        void Shopware.Store.get('notification').createNotification({
            variant: 'attention',
            title: 'global.default.warning',
            message: 'sw-settings-basic-information.sellerCountryNotification.message',
            growl: true,
            actions: [
                {
                    // Action labels are rendered as they are, unlike the title and the message
                    label: (Shopware.Application.getApplicationRoot() as App<Element>).$t(
                        'sw-settings-basic-information.sellerCountryNotification.openSettings',
                    ),
                    route: { name: 'sw.settings.basic.information.index' },
                },
            ],
        });
    }
}
