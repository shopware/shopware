import Vue from 'vue';
import { notification, context, data, window } from '@shopware-ag/admin-extension-sdk';

const { repository, Classes: { Criteria } } = data;

export default Vue.extend({
    template: `
        <div>
            <h1>Hello from the new Menu Page</h1>

            <div>
                <h3>Context - Get current language</h3>
                <button @click="getLanguage">Get current language</button>

                <p>
                    system-language-ID: {{ systemLanguageId }}
                    <br />
                    languageId: {{ languageId }}
                </p>
            </div>

            <div>
                <h3>Context - Get current environment</h3>
                <button @click="getEnvironment">Get current environment</button>

                <p>
                    Environment: {{ environment }}
                </p>
            </div>

            <div>
                <h3>Context - Get current locale</h3>
                <button @click="getLocale">Get current locale</button>

                <p>
                    Locale: {{ locale }}
                    <br />
                    Fallback Locale: {{ fallbackLocale }}
                </p>
            </div>

            <div>
                <h3>Context - Get current currency</h3>
                <button @click="getCurrency">Get current currency</button>

                <p>
                    System Currency Id: {{ systemCurrencyId }}
                    <br />
                    System Currency ISOCode: {{ systemCurrencyISOCode }}
                </p>
            </div>

            <div>
                <h3>Context - Get current Shopware version</h3>
                <button @click="getShopwareVersion">Get current Shopware version</button>

                <p>
                    Shopware version: {{ shopwareVersion }}
                </p>
            </div>

            <div>
                <h3>Context - Get app information</h3>
                <button @click="getAppInformation">Get app information</button>

                <p>
                    App name: {{ appName }}
                    <br>
                    App version (empty in plugins): {{ appVersion }}
                    <br>
                    App type: {{ appType }}
                </p>
            </div>

            <div>
                <h3>Context - Get module information</h3>
                <button @click="getModuleInformation">Get module information</button>

                <p v-for="moduleInformation in moduleInformations">
                    Id: {{ moduleInformation.id }}
                    <br>
                    Display search bar: {{ moduleInformation.displaySearchBar }}
                    <br>
                    Heading: {{ moduleInformation.heading }}
                    <br>
                    LocationId: {{ moduleInformation.locationId }}
                </p>
            </div>

            <div>
                <h3>Window - Router Push</h3>
                <button @click="pushRoute">Push route</button>
                <br>
                <textarea cols="30" rows="10" v-model="routeInformation"></textarea>
            </div>

            <div>
                <h3>Notification - Dispatch a notification</h3>

                <button @click="dispatchNotification">Dispatch a notification</button>
            </div>

            <div>
                <h3>Notification - Reload page</h3>

                <button @click="reloadPage">Reload page</button>
            </div>

            <div id="dbquery">
                <div class="test">Test</div>
                <button @click="getApilanguage" id="getlanguage">DB Query</button>
                <input v-if="apiLanguageEntity" type="text" name="apilanguage" id="apiLanguage" v-model="apiLanguageEntity.name">
                <button @click="saveApilangugage" id="saveusername">Save name</button>
            </div>
        </div>
    `,
    data() {
        return {
            languageId: '',
            systemLanguageId: '',
            environment: '',
            locale: '',
            fallbackLocale: '',
            systemCurrencyId: '',
            systemCurrencyISOCode: '',
            shopwareVersion: '',
            appName: '',
            appType: '',
            appVersion: '',
            moduleInformations: [],
            routeInformation:  `
{
    "name": "sw.dashboard.index",
    "params": {}
}`,
            apiLanguageEntity: null,
        }
    },
    methods: {
        // context / get current language
        getLanguage() {
            context.getLanguage().then(language => {
                this.languageId = language.languageId;
                this.systemLanguageId = language.systemLanguageId;
            })
        },

        // context / get current environment
        async getEnvironment() {
            this.environment = await context.getEnvironment();
        },

        // context / get current locale
        getLocale() {
            context.getLocale().then(({ locale, fallbackLocale }) => {
                this.locale = locale;
                this.fallbackLocale = fallbackLocale;
            })
        },

        // context / get current locale
        getCurrency() {
            context.getCurrency().then(({ systemCurrencyId, systemCurrencyISOCode }) => {
                this.systemCurrencyId = systemCurrencyId;
                this.systemCurrencyISOCode = systemCurrencyISOCode;
            })
        },

        // context / get current Shopware version
        async getShopwareVersion() {
            this.shopwareVersion = await context.getShopwareVersion();
        },

        // context / get App information
        getAppInformation() {
            context.getAppInformation().then(({ name, type, version }) => {
                this.appName = name;
                this.appVersion = version;
                this.appType = type;
            })
        },

        // context / get module information
        getModuleInformation() {
            context.getModuleInformation().then(({ modules }) => {
                // @ts-ignore
                this.moduleInformations = modules;
            })
        },

        // window / router push
        pushRoute() {
            let routeObject = {};

            try {
                routeObject = JSON.parse(this.routeInformation);
            } catch (e){
                notification.dispatch({
                    title: 'Push route',
                    message: 'The content of the textarea could not be parsed'
                })
            }

            void window.routerPush(routeObject);
        },

        dispatchNotification() {
            notification.dispatch({
                title: 'Your title',
                message: 'Your message',
                variant: 'success',
                appearance: 'notification',
                growl: true,
                actions: [
                    {
                        label: 'Yes',
                        method: () => {
                            alert('Yes')
                        }
                    },
                    {
                        label: 'No',
                        method: () => {
                            alert('No')
                        }
                    },
                    {
                        label: 'Redirect to Shopware',
                        method: () => {
                            window.redirect({
                                url: 'https://www.shopware.com',
                                newTab: true,
                            })
                        },
                    }
                ]
            })
        },

        reloadPage() {
            void window.reload();
        },

        getApilanguage() {
            const exampleCriteria = new Criteria();
            repository('language').search(exampleCriteria).then(response => {
                if (!response) {
                    return;
                }
                // @ts-expect-error
                this.apiLanguageEntity = response.first();
            })
        },
        saveApilangugage() {
            repository('language').save(this.apiLanguageEntity)
                .then(() => {
                    notification.dispatch({
                        title: 'Speicherung erfolgreich',
                        message: 'Sprache wurde gespeichert',
                        variant: "success"
                    })

                    this.getApilanguage()
                }).catch(() => {
                    notification.dispatch({
                        title: 'Error',
                        message: 'The Language could not be saved',
                        variant: 'error',
                    })
            })
        }
    }
})
