import template from './sw-meteor-card.html.twig';
import './sw-meteor-card.scss';

const { Component } = Shopware;

/**
 * @public
 * @description A card is a flexible and extensible content container.
 * @status ready
 * @example-type static
 * @example-description This example illustrates the usage of tabs with this component.
 * @component-example
 * <sw-meteor-card title="Card title">
 *      <template #tabs>
 *          <sw-tabs :small="false" @new-item-active="onNewActiveItem">
 *              <sw-tabs-item name="tab1" key="tab1" :active="active">
 *                  Tab 1
 *              </sw-tabs-item>
 *              <sw-tabs-item name="tab2" key="tab2">
 *                  Tab 2
 *              </sw-tabs-item>
 *          </sw-tabs>
 *      </template>
 *
 *      {# use div instead of template tag for the default slot if tab contents use different slots #}
 *      <div v-if="active === 'tab1'">
 *          <p>Tab 1 content</p>
 *      </div>
 *      <template #grid v-if="active === 'tab2'">
 *          <sw-grid :items="['Row 1', 'Row 2']" :selectable="false">
 *              <template #columns="{ item }">
 *                  <sw-grid-column label="Grid label">
 *                      {{ item }}
 *                  </sw-grid-column>
 *              </template>
 *          </sw-grid>
 *      </template>
 * </sw-meteor-card>
 */
Component.register('sw-meteor-card', {
    template,

    props: {
        title: {
            type: String,
            required: false
        },
        hero: {
            type: Boolean,
            required: false,
            default: false
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        },
        large: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        hasTabs() {
            return !!this.$slots.tabs || !!this.$scopedSlots.tabs;
        },

        hasToolbar() {
            return !!this.$slots.toolbar || !!this.$scopedSlots.toolbar;
        },

        hasHeader() {
            return this.hasToolbar || this.hasTabs || !!this.title || !!this.$slots.action;
        },

        isToolbarLastHeaderElement() {
            return this.hasToolbar && !this.hasTabs;
        },

        cardClasses() {
            return {
                'sw-meteor-card--tabs': this.hasTabs,
                'sw-meteor-card--toolbar': this.hasToolbar,
                'sw-meteor-card--hero': !!this.hero,
                'sw-meteor-card--large': this.large,
                'has--header': this.hasHeader && !this.isToolbarLastHeaderElement
            };
        }
    }
});
