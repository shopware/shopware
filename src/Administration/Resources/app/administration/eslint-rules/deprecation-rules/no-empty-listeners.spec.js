const { RuleTester } = require('eslint');
const rule = require('./no-empty-listeners');

const ruleTester = new RuleTester({
    parser: require.resolve('@typescript-eslint/parser'),
    parserOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
    },
});

ruleTester.run('no-empty-listeners', rule, {
    valid: [
        {
            name: 'Object without listeners() method',
            code: `
        export default {
          computed: {
            someMethod() {
              // Some code
            }
          }
        }
      `,
        },
        {
            name: 'Object with non-empty listeners() method',
            code: `
        export default {
          computed: {
            listeners() {
              return { click: () => {} };
            }
          }
        }
      `,
        },
        {
            name: 'Object with listeners property that is not a method',
            code: `
        export default {
          computed: {
            listeners: { click: () => {} }
          }
        }
      `,
        },
    ],
    invalid: [
        {
            name: 'Object with empty listeners() method',
            code: `
        export default {
          computed: {
            listeners() {
              return {};
            }
          }
        }
      `,
            output: `
        export default {
          computed: {}
        }
      `,
            errors: [
                {
                    message: 'Empty listeners() method should be removed for Vue 3 migration',
                    type: 'Property',
                },
            ],
        },
        {
            name: 'Object with empty listeners() method',
            code: `
        export default {
          computed: {
            onSave() {
             // Do something
            },
            listeners() {
              return {};
            }
          }
        }
      `,
            output: `
        export default {
          computed: {
            onSave() {
             // Do something
            }
          }
        }
      `,
            errors: [
                {
                    message: 'Empty listeners() method should be removed for Vue 3 migration',
                    type: 'Property',
                },
            ],
        },
        {
            name: 'Object with empty listeners() method and Component.register',
            code: `
import template from './sw-button.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-button and mt-button. Autoswitches between the two components.
 */
Component.register('sw-button', {
    template,

    compatConfig: {
        ...Shopware.compatConfig,
        // Needed so that Button classes are bound correctly via \`v-bind="$attrs"\`
        INSTANCE_ATTRS_CLASS_STYLE: false,
    },

    props: {
        routerLink: {
            type: [String, Object],
            default: null,
            required: false,
        },
    },

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-button',
                // eslint-disable-next-line max-len
                'The old usage of "sw-button" is deprecated and will be removed in v6.7.0.0. Please use "mt-button" instead.',
            );

            return false;
        },

        listeners() {
            return {};
        },
    },

    methods: {
        onClick() {
            // Important: Do not emit the click event again, it is already emitted by the button

            // Check if deprecated routerLink is used
            if (this.routerLink) {
                // Use router push to navigate to the new page
                this.$router.push(this.routerLink);
            }
        },
    },
});
      `,
            output: `
import template from './sw-button.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-button and mt-button. Autoswitches between the two components.
 */
Component.register('sw-button', {
    template,

    compatConfig: {
        ...Shopware.compatConfig,
        // Needed so that Button classes are bound correctly via \`v-bind="$attrs"\`
        INSTANCE_ATTRS_CLASS_STYLE: false,
    },

    props: {
        routerLink: {
            type: [String, Object],
            default: null,
            required: false,
        },
    },

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-button',
                // eslint-disable-next-line max-len
                'The old usage of "sw-button" is deprecated and will be removed in v6.7.0.0. Please use "mt-button" instead.',
            );

            return false;
        },
    },

    methods: {
        onClick() {
            // Important: Do not emit the click event again, it is already emitted by the button

            // Check if deprecated routerLink is used
            if (this.routerLink) {
                // Use router push to navigate to the new page
                this.$router.push(this.routerLink);
            }
        },
    },
});
      `,
            errors: [
                {
                    message: 'Empty listeners() method should be removed for Vue 3 migration',
                    type: 'Property',
                },
            ],
        },
        {
          name: 'Object with empty listeners() method and other methods',
          code: `
            export default {
              computed: {
                listeners() {
                  return {};
                },
                otherMethod() {
                  // Some code
                }
              }
            }
          `,
          output: `
            export default {
              computed: {
                otherMethod() {
                  // Some code
                }
              }
            }
          `,
          errors: [
            {
              message: 'Empty listeners() method should be removed for Vue 3 migration',
              type: 'Property',
            },
          ],
        },
        {
            name: 'Object with empty listeners() method using arrow function',
            code: `
        export default {
          computed: {
            listeners: () => ({})
          }
        }
      `,
            output: `
        export default {
          computed: {}
        }
      `,
            errors: [
                {
                    message: 'Empty listeners() method should be removed for Vue 3 migration',
                    type: 'Property',
                },
            ],
        },
        {
            name: 'Object with empty listeners() method (fix disabled)',
            code: `
        export default {
          computed: {
            listeners() {
              return {};
            }
          }
        }
      `,
            options: ['disableFix'],
            errors: [
                {
                    message: 'Empty listeners() method should be removed for Vue 3 migration',
                    type: 'Property',
                },
            ],
        },
    ],
});
