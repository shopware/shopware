import { storiesOf } from '@storybook/vue';
import vueComponents from '../helper/components.collector';
import SwagVueInfoAddon from '../addons/info-addon';

storiesOf('sw-sidebar', module)
    .addDecorator(SwagVueInfoAddon)
    .add('Basic usage', () => ({
        components: {
            'sw-sidebar': vueComponents.get('sw-sidebar'),
            'sw-sidebar-item': vueComponents.get('sw-sidebar-item')
        },
        template: `
            <div>
                <sw-sidebar>
                    <sw-sidebar-item icon="default-award-trophy"></sw-sidebar-item>
                    <sw-sidebar-item icon="default-chart-line" :disabled="true"></sw-sidebar-item>
                    <sw-sidebar-item icon="default-device-dashboard"></sw-sidebar-item>
                    <sw-sidebar-item icon="default-device-headset"></sw-sidebar-item>
                    <sw-sidebar-item icon="default-lock-fingerprint"></sw-sidebar-item>
                    <sw-sidebar-item icon="default-action-settings"></sw-sidebar-item>
                    <sw-sidebar-item icon="default-avatar-multiple"></sw-sidebar-item>
                </sw-sidebar>
            </div>
        `
    }))
    .add('Sidebar Item with content', () => ({
        components: {
            'sw-sidebar-item': vueComponents.get('sw-sidebar-item'),
            'sw-sidebar': vueComponents.get('sw-sidebar')
        },
        template: `
            <div>
                 <sw-sidebar style="margin-left: 280px; position: relative;">
                    <sw-sidebar-item icon="default-award-trophy" title="Example #1">
                        <p>
                            Lorem ipsum dolor sit amet, consectetur adipisicing elit. A ad at consequuntur culpa cum distinctio eligendi esse fuga impedit, itaque nihil praesentium quisquam quos reiciendis repellendus saepe similique veritatis voluptas.
                        </p>
                    </sw-sidebar-item>
                    <sw-sidebar-item icon="default-chart-line" title="Example #2">
                        <p>
                            Lorem ipsum dolor sit amet, consectetur adipisicing elit. A ad at consequuntur culpa cum distinctio eligendi esse fuga impedit, itaque nihil praesentium quisquam quos reiciendis repellendus saepe similique veritatis voluptas.
                        </p>
                    </sw-sidebar-item>
                    <sw-sidebar-item icon="default-device-dashboard" title="Example #3">
                        <p>
                            Lorem ipsum dolor sit amet, consectetur adipisicing elit. A ad at consequuntur culpa cum distinctio eligendi esse fuga impedit, itaque nihil praesentium quisquam quos reiciendis repellendus saepe similique veritatis voluptas.
                        </p>
                    </sw-sidebar-item>
                    <sw-sidebar-item icon="default-device-headset" title="Example #4">
                        <p>
                            Lorem ipsum dolor sit amet, consectetur adipisicing elit. A ad at consequuntur culpa cum distinctio eligendi esse fuga impedit, itaque nihil praesentium quisquam quos reiciendis repellendus saepe similique veritatis voluptas.
                        </p>
                    </sw-sidebar-item>
                    <sw-sidebar-item icon="default-lock-fingerprint" title="Example #5">
                        <p>
                            Lorem ipsum dolor sit amet, consectetur adipisicing elit. A ad at consequuntur culpa cum distinctio eligendi esse fuga impedit, itaque nihil praesentium quisquam quos reiciendis repellendus saepe similique veritatis voluptas.
                        </p>
                    </sw-sidebar-item>
                    <sw-sidebar-item icon="default-action-settings" title="Example #6">
                        <p>
                            Lorem ipsum dolor sit amet, consectetur adipisicing elit. A ad at consequuntur culpa cum distinctio eligendi esse fuga impedit, itaque nihil praesentium quisquam quos reiciendis repellendus saepe similique veritatis voluptas.
                        </p>
                    </sw-sidebar-item>
                    <sw-sidebar-item icon="default-avatar-multiple" title="Example #7">
                        <p>
                            Lorem ipsum dolor sit amet, consectetur adipisicing elit. A ad at consequuntur culpa cum distinctio eligendi esse fuga impedit, itaque nihil praesentium quisquam quos reiciendis repellendus saepe similique veritatis voluptas.
                        </p>
                    </sw-sidebar-item>
                </sw-sidebar>
            </div>
        `
    }));
