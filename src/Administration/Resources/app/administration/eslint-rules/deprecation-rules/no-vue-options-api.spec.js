const { RuleTester } = require('eslint');
const rule = require('./no-vue-options-api');

const ruleTester = new RuleTester({
    parser: require.resolve('@typescript-eslint/parser'),
});

/**
 * BIG TODO: PUBLIC_API and Extension System is not defined yet
 */

ruleTester.run('vue2-to-vue3-composition', rule, {
    valid: [
        {
            name: 'Valid: Vue 3 Composition API syntax (no errors)',
            code: `
      export default {
        setup() {
          const count = ref(0);
          const doubleCount = computed(() => count.value * 2);
          const increment = () => count.value++;
          watch(() => count.value, (newValue, oldValue) => console.log('Count changed:', newValue, oldValue));

          return { count, doubleCount, increment };
        }
      };
      `,
        },
        {
            name: 'Default export object which is not a Vue component (no errors)',
            code: `
            export default {
                get(key) {
                    const hash = crypto.createHash('sha1');
                    hash.update(key);
                    return hash.digest('hex');
                },
            };
            `,
        },
    ],
    invalid: [
        {
            name: 'Invalid without fix: Vue 2 Options API with disableFix option (no fix applied)',
            code: `
      export default {
        data() {
          return { count: 0 };
        }
      };
      `,
            options: ['disableFix'],
            errors: [{ message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' }],
        },
        {
            name: 'Invalid: Convert data() to ref in setup()',
            code: `
      export default {
        data() {
          return {
            count: 0
          };
        }
      };
      `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: `
      import { ref } from 'vue';
export default {
        setup() {
          const count = ref(0);

          return {
            count,
          };
        },

      };
      `,
        },
        {
            name: 'Invalid: Convert data() to reactive in setup()',
            code: `
      export default {
        data() {
          return {
            someObject: {
              count: 0
            }
          };
        }
      };
      `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: `
      import { reactive } from 'vue';
export default {
        setup() {
          const someObject = reactive({
              count: 0
            });

          return {
            someObject,
          };
        },

      };
      `,
        },
        {
            name: 'Invalid: Convert data() to reactive with reassignment in setup()',
            code: `
      export default {
        data() {
          return {
            tax: {}
          };
        },
        methods: {
            setTax() {
                this.tax = { rate: 1 };
            }
        }
      };
      `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
                '\n' +
                "      import { reactive } from 'vue';\n" +
                'export default {\n' +
                '        setup() {\n' +
                '          const tax = reactive({});\n' +
                '      const setTax = () => {\n' +
                '                Object.assign(tax, { rate: 1 });\n' +
                '            };\n' +
                '\n' +
                '          return {\n' +
                '            tax,\n' +
                '            setTax,\n' +
                '          };\n' +
                '        },\n' +
                '\n' +
                '        \n' +
                '      };\n' +
                '      ',
        },
        {
            name: 'Invalid: Convert data() to reactive with multi-line reassignment in setup()',
            code: `
      export default {
        data() {
          return {
            numberRange: {}
          };
        },
        methods: {
            async loadEntityData() {
                this.numberRange = await this.numberRangeRepository.get(
                    this.numberRangeId,
                    Shopware.Context.api,
                    this.numberRangeCriteria,
                );
            }
        }
      };
      `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
                '\n' +
                "      import { reactive } from 'vue';\n" +
                'export default {\n' +
                '        setup() {\n' +
                '          const numberRange = reactive({});\n' +
                '      const loadEntityData = async () => {\n' +
                '                Object.assign(numberRange, await this.numberRangeRepository.get(\n' +
                '                    this.numberRangeId,\n' +
                '                    Shopware.Context.api,\n' +
                '                    this.numberRangeCriteria,\n' +
                '                ))\n' +
                ';\n' +
                '            };\n' +
                '\n' +
                '          return {\n' +
                '            numberRange,\n' +
                '            loadEntityData,\n' +
                '          };\n' +
                '        },\n' +
                '\n' +
                '        \n' +
                '      };\n' +
                '      ',
        },
        {
            name: 'Invalid: Convert data() to reactive and ref combined in setup()',
            code: `
      export default {
        data() {
          return {
            someObject: {
              count: 0
            },
            message: 'Hello World'
          };
        }
      };
      `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: `
      import { reactive, ref } from 'vue';
export default {
        setup() {
          const someObject = reactive({
              count: 0
            });
      const message = ref('Hello World');

          return {
            someObject,
            message,
          };
        },

      };
      `,
        },
        {
            name: 'Invalid: Convert normal computed properties to computed() in setup()',
            code: `
      export default {
        data() {
          return {
            count: 0
          };
        },
        computed: {
          doubleCount() {
            return this.count * 2;
          }
        },};
      `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: `
      import { ref, computed } from 'vue';
export default {
        setup() {
          const count = ref(0);
          const doubleCount = computed(() => {
            return count.value * 2;
          });

          return {
            count,
            doubleCount,
          };
        },

        };
      `,
        },
        {
            name: 'Invalid: Convert computed property with getter and setter to computed() in setup()',
            code: `
      export default {
        data() {
          return {
            count: 0
          };
        },
        computed: {
          doubleCount: {
            get() {
                return this.count * 2;
            },
            set(value) {
                this.count = value / 2;
            }
          }
        }};
      `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: `
      import { ref, computed } from 'vue';
export default {
        setup() {
          const count = ref(0);
          const doubleCount = computed({
            get() {
                return count.value * 2;
            },
            set(value) {
                count.value = value / 2;
            }
          });

          return {
            count,
            doubleCount,
          };
        },

        };
      `,
        },
        {
            name: 'Invalid: Convert methods to functions in setup()',
            code: `
      export default {
        methods: {
          increment() {
            this.count++;
          }
        }
      };
      `,
            errors: [{ message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' }],
            output: `
      export default {
        setup() {
          const increment = () => {
            this.count++;
          };

          return {
            increment,
          };
        },

      };
      `,
        },
        {
            name: 'Invalid: Convert methods with debounce to functions in setup()',
            code: `
      export default {
        methods: {
        foo() {
            // Do something
            },
          debouncedSearch: debounce(function debouncedSearch() {
            // Do something
          }, 500),

          doChange: utils.debounce(function doChange() {
            // Do something
          }, 300),
        }
      };
      `,
            errors: [{ message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' }],
            output: `
      export default {
        setup() {
          const foo = () => {
            // Do something
            };
      const debouncedSearch = debounce(function debouncedSearch() {
            // Do something
          }, 500);
      const doChange = utils.debounce(function doChange() {
            // Do something
          }, 300);

          return {
            foo,
            debouncedSearch,
            doChange,
          };
        },

      };
      `,
        },
        {
            name: 'Invalid: Convert methods with TS types to functions in setup()',
            code: `
      export default {
        methods: {
            search(): void {
                // Do something
            },
            onSave(data: any): void {
                // Do something
            },
            async wait(): void {
                // Do something
            },
        }
      };
      `,
            errors: [{ message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' }],
            output: `
      export default {
        setup() {
          const search = (): void => {
                // Do something
            };
      const onSave = (data: any): void => {
                // Do something
            };
      const wait = async (): void => {
                // Do something
            };

          return {
            search,
            onSave,
            wait,
          };
        },

      };
      `,
        },
        {
            name: 'Invalid: Convert async methods to functions in setup()',
            code: `
      export default {
        methods: {
          async onSave() {
            await doSomething();
          }
        }
      };
      `,
            errors: [{ message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' }],
            output: `
      export default {
        setup() {
          const onSave = async () => {
            await doSomething();
          };

          return {
            onSave,
          };
        },

      };
      `,
        },
        {
            name: 'Invalid: Accessing props in setup()',
            code: `
      export default {
      props: ['count'],
        methods: {
          increment() {
            this.count++;
          }
        }
      };
      `,
            errors: [{ message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' }],
            output: '' +
'\n' +
'      export default {\n' +
'      setup(props) {\n' +
'          const increment = () => {\n' +
'            props.count++;\n' +
'          };\n' +
'\n' +
'          return {\n' +
'            increment,\n' +
'          };\n' +
'        },\n' +
"props: ['count'],\n" +
'        \n' +
'      };\n' +
'      ',
        },
        {
            name: 'Invalid: Convert watch to watch() in setup()',
            code: `
      export default {
        watch: {
          count(newValue, oldValue) {
            console.log('Count changed:', newValue, oldValue);
          }
        }
      };
      `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: `
      import { watch } from 'vue';
export default {
        setup() {
          watch(count, (newValue, oldValue) => {
            console.log('Count changed:', newValue, oldValue);
          });

          return {
          };
        },

      };
      `,
        },
        {
            name: 'Invalid: Convert watch() with correct access to props',
            code: `
        export default {
            props: ['count'],
            watch: {
                count(newValue, oldValue) {
                    console.log('Count changed:', newValue, oldValue);
                }
            }
        };
        `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
'\n' +
"        import { watch } from 'vue';\n" +
'export default {\n' +
'            setup(props) {\n' +
'          watch(props.count, (newValue, oldValue) => {\n' +
"                    console.log('Count changed:', newValue, oldValue);\n" +
'                });\n' +
'\n' +
'          return {\n' +
'          };\n' +
'        },\n' +
"props: ['count'],\n" +
'            \n' +
'        };\n' +
'        ',
        },
        {
            name: 'Invalid: Convert watch with handler and deep to watch() in setup()',
            code: `
      export default {
        watch: {
          count: {
            handler(newValue, oldValue) {
              console.log('Count changed:', newValue, oldValue);
            },
            deep: true
          }
        }
      };
      `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: `
      import { watch } from 'vue';
export default {
        setup() {
          watch(count, (newValue, oldValue) => {
              console.log('Count changed:', newValue, oldValue);
            }, { deep: true });

          return {
          };
        },

      };
      `,
        },
        {
            name: 'Invalid: Convert watch with handler and immediate to watch() in setup()',
            code: `
      export default {
        watch: {
          count: {
            handler(newValue, oldValue) {
              console.log('Count changed:', newValue, oldValue);
            },
            immediate: true
          }
        }
      };
      `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: `
      import { watch } from 'vue';
export default {
        setup() {
          watch(count, (newValue, oldValue) => {
              console.log('Count changed:', newValue, oldValue);
            }, { immediate: true });

          return {
          };
        },

      };
      `,
        },
        {
            name: 'Invalid: Convert watch with handler and deep + immediate to watch() in setup()',
            code: `
      export default {
        watch: {
          count: {
            handler(newValue, oldValue) {
              console.log('Count changed:', newValue, oldValue);
            },
            immediate: true,
            deep: true
          }
        }
      };
      `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: `
      import { watch } from 'vue';
export default {
        setup() {
          watch(count, (newValue, oldValue) => {
              console.log('Count changed:', newValue, oldValue);
            }, { immediate: true, deep: true });

          return {
          };
        },

      };
      `,
        },
        {
            name: 'Invalid: Convert "onMounted" lifecycle hook to Composition API',
            code: `
        export default {
            mounted() {
                console.log('Component mounted');
            }
        };`,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
'\n' +
'        import { onMounted } from \'vue\';\n' +
'export default {\n' +
'            setup() {\n' +
'          onMounted(() => {\n' +
"                console.log('Component mounted');\n" +
'            });\n' +
'\n' +
'          return {\n' +
'          };\n' +
'        },\n' +
'\n' +
'        };',
        },
        {
            name: 'Invalid: Convert "onUpdated" lifecycle hook to Composition API',
            code: `
        export default {
            updated() {
                console.log('Component updated');
            }
        };`,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
'\n' +
'        import { onUpdated } from \'vue\';\n' +
'export default {\n' +
'            setup() {\n' +
'          onUpdated(() => {\n' +
"                console.log('Component updated');\n" +
'            });\n' +
'\n' +
'          return {\n' +
'          };\n' +
'        },\n' +
'\n' +
'        };',
        },
        {
            name: 'Invalid: Convert "onUnmounted" lifecycle hook to Composition API',
            code: `
        export default {
            unmounted() {
                console.log('Component unmounted');
            }
        };`,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
'\n' +
'        import { onUnmounted } from \'vue\';\n' +
'export default {\n' +
'            setup() {\n' +
'          onUnmounted(() => {\n' +
"                console.log('Component unmounted');\n" +
'            });\n' +
'\n' +
'          return {\n' +
'          };\n' +
'        },\n' +
'\n' +
'        };',
        },
        {
            name: 'Invalid: Convert "onBeforeMount" lifecycle hook to Composition API',
            code: `
        export default {
            beforeMount() {
                console.log('Component before mount');
            }
        };`,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
'\n' +
'        import { onBeforeMount } from \'vue\';\n' +
'export default {\n' +
'            setup() {\n' +
'          onBeforeMount(() => {\n' +
"                console.log('Component before mount');\n" +
'            });\n' +
'\n' +
'          return {\n' +
'          };\n' +
'        },\n' +
'\n' +
'        };',
        },
        {
            name: 'Invalid: Convert "onBeforeUpdate" lifecycle hook to Composition API',
            code: `
        export default {
            beforeUpdate() {
                console.log('Component before update');
            }
        };`,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
'\n' +
'        import { onBeforeUpdate } from \'vue\';\n' +
'export default {\n' +
'            setup() {\n' +
'          onBeforeUpdate(() => {\n' +
"                console.log('Component before update');\n" +
'            });\n' +
'\n' +
'          return {\n' +
'          };\n' +
'        },\n' +
'\n' +
'        };',
        },
        {
            name: 'Invalid: Convert "onBeforeUnmount" lifecycle hook to Composition API',
            code: `
        export default {
            beforeUnmount() {
                console.log('Component before unmount');
            }
        };`,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
'\n' +
'        import { onBeforeUnmount } from \'vue\';\n' +
'export default {\n' +
'            setup() {\n' +
'          onBeforeUnmount(() => {\n' +
"                console.log('Component before unmount');\n" +
'            });\n' +
'\n' +
'          return {\n' +
'          };\n' +
'        },\n' +
'\n' +
'        };',
        },
        {
            name: 'Invalid: Convert multiple Vue 2 Options API properties (data, computed, methods, watch) to Composition API',
            code: `
      export default {
        data() {
          return { count: 0 };
        },
        computed: {
          doubleCount() {
            return this.count * 2;
          }
        },
        methods: {
          increment() {
            this.count++;
          }
        },
        watch: {
          count(newValue, oldValue) {
            console.log('Count changed:', newValue, oldValue);
          }
        }
    };`,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: "" +
                '\n' +
                '      import { ref, computed, watch } from \'vue\';\n' +
                'export default {\n' +
                '        setup() {\n' +
                '          const count = ref(0);\n' +
                '          const doubleCount = computed(() => {\n' +
                '            return count.value * 2;\n' +
                '          });\n' +
                '      const increment = () => {\n' +
                '            count.value++;\n' +
                '          };\n' +
                '      watch(count, (newValue, oldValue) => {\n' +
                "            console.log('Count changed:', newValue, oldValue);\n" +
                '          });\n' +
                '\n' +
                '          return {\n' +
                '            count,\n' +
                '            doubleCount,\n' +
                '            increment,\n' +
                '          };\n' +
                '        },\n' +
                '\n' +
                '        \n' +
                '        \n' +
                '        \n' +
                '    };',
        },
        {
            name: 'Invalid: Real world example 1',
            code: `
/**
 * @package services-settings
 */
import template from './sw-users-permissions-role-view-general.html.twig';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'acl',
    ],

    props: {
        role: {
            type: Object,
            required: true,
        },
    },

    computed: {
        ...mapPropertyErrors('role', [
            'name',
            'description',
        ]),
    },
};
            `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '\n' +
                '/**\n' +
                ' * @package services-settings\n' +
                ' */\n' +
                "import { inject } from 'vue';\n" +
                "import template from './sw-users-permissions-role-view-general.html.twig';\n" +
                '\n' +
                'const { mapPropertyErrors } = Shopware.Component.getComponentHelper();\n' +
                '\n' +
                'export default {\n' +
                '    setup() {\n' +
                '    \n' +
                '    /** TODO: Spread computed property is not fully supported yet. Original code:\n' +
                "        mapPropertyErrors('role', [\n" +
                "            'name',\n" +
                "            'description',\n" +
                '        ])\n' +
                '    */\n' +
                "      const acl = inject('acl');\n" +
                '\n' +
                '          return {\n' +
                '            acl,\n' +
                '          };\n' +
                '        },\n' +
                'template,\n' +
                '\n' +
                '    compatConfig: Shopware.compatConfig,\n' +
                '\n' +
                '    \n' +
                '\n' +
                '    props: {\n' +
                '        role: {\n' +
                '            type: Object,\n' +
                '            required: true,\n' +
                '        },\n' +
                '    },\n' +
                '\n' +
                '    \n' +
                '};\n' +
                '            ',
        },
        {
            name: 'Invalid: Real world example 2',
            code: `
import template from './sw-users-permissions-user-detail.html.twig';
import './sw-users-permissions-user-detail.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'userService',
        'loginService',
        'mediaDefaultFolderService',
        'userValidationService',
        'integrationService',
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            isLoading: false,
            userId: '',
            user: null,
            currentUser: null,
            languages: [],
            integrations: [],
            currentIntegration: null,
            mediaItem: null,
            newPassword: '',
            newPasswordConfirm: '',
            /**
             * @deprecated tag:v6.7.0 - Will be removed. Use \`isEmailAlreadyInUse\` instead
             */
            isEmailUsed: false,
            isEmailAlreadyInUse: false,
            isUsernameUsed: false,
            isIntegrationsLoading: false,
            isSaveSuccessful: false,
            isModalLoading: false,
            showSecretAccessKey: false,
            showDeleteModal: null,
            skeletonItemAmount: 3,
            confirmPasswordModal: false,
            timezoneOptions: [],
            mediaDefaultFolderId: null,
            showMediaModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        ...mapPropertyErrors('user', [
            'firstName',
            'lastName',
            'email',
            'username',
            'localeId',
            'password',
        ]),

        identifier() {
            return this.fullName;
        },
    },

    watch: {
        languageId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        checkUsername() {
            return this.userValidationService.checkUserUsername({
                username: this.user.username,
                id: this.user.id,
            }).then(({ usernameIsUnique }) => {
                this.isUsernameUsed = !usernameIsUnique;
            });
        },

        loadMediaItem(targetId) {
            this.mediaRepository.get(targetId).then((media) => {
                this.mediaItem = media;
                this.user.avatarMedia = media;
            });
        },
    },
};
`,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
                '\n' +
                "import { ref, inject, computed, onBeforeMount, watch } from 'vue';\n" +
                "import template from './sw-users-permissions-user-detail.html.twig';\n" +
                "import './sw-users-permissions-user-detail.scss';\n" +
                '\n' +
                'const { Component, Mixin } = Shopware;\n' +
                'const { mapPropertyErrors } = Component.getComponentHelper();\n' +
                '\n' +
                'export default {\n' +
                '    setup() {\n' +
                '          const isLoading = ref(false);\n' +
                "      const userId = ref('');\n" +
                '      const user = ref(null);\n' +
                '      const currentUser = ref(null);\n' +
                '      const languages = ref(undefined);\n' +
                '      const integrations = ref(undefined);\n' +
                '      const currentIntegration = ref(null);\n' +
                '      const mediaItem = ref(null);\n' +
                "      const newPassword = ref('');\n" +
                "      const newPasswordConfirm = ref('');\n" +
                '      const isEmailUsed = ref(false);\n' +
                '      const isEmailAlreadyInUse = ref(false);\n' +
                '      const isUsernameUsed = ref(false);\n' +
                '      const isIntegrationsLoading = ref(false);\n' +
                '      const isSaveSuccessful = ref(false);\n' +
                '      const isModalLoading = ref(false);\n' +
                '      const showSecretAccessKey = ref(false);\n' +
                '      const showDeleteModal = ref(null);\n' +
                '      const skeletonItemAmount = ref(3);\n' +
                '      const confirmPasswordModal = ref(false);\n' +
                '      const timezoneOptions = ref(undefined);\n' +
                '      const mediaDefaultFolderId = ref(null);\n' +
                '      const showMediaModal = ref(false);\n' +
                '\n' +
                '    /** TODO: Spread computed property is not fully supported yet. Original code:\n' +
                "        mapPropertyErrors('user', [\n" +
                "            'firstName',\n" +
                "            'lastName',\n" +
                "            'email',\n" +
                "            'username',\n" +
                "            'localeId',\n" +
                "            'password',\n" +
                '        ])\n' +
                '    */\n' +
                "      const userService = inject('userService');\n" +
                "      const loginService = inject('loginService');\n" +
                "      const mediaDefaultFolderService = inject('mediaDefaultFolderService');\n" +
                "      const userValidationService = inject('userValidationService');\n" +
                "      const integrationService = inject('integrationService');\n" +
                "      const repositoryFactory = inject('repositoryFactory');\n" +
                "      const acl = inject('acl');\n" +
                '          const identifier = computed(() => {\n' +
                '            return this.fullName;\n' +
                '        });\n' +
                '      const checkUsername = () => {\n' +
                '            return userValidationService.checkUserUsername({\n' +
                '                username: user.value.username,\n' +
                '                id: user.value.id,\n' +
                '            }).then(({ usernameIsUnique }) => {\n' +
                '                isUsernameUsed.value = !usernameIsUnique;\n' +
                '            });\n' +
                '        };\n' +
                '      const loadMediaItem = (targetId) => {\n' +
                '            this.mediaRepository.get(targetId).then((media) => {\n' +
                '                mediaItem.value = media;\n' +
                '                user.value.avatarMedia = media;\n' +
                '            });\n' +
                '        };\n' +
                '      onBeforeMount(() => {\n' +
                '        this.createdComponent();\n' +
                '    });\n' +
                '      watch(languageId, () => {\n' +
                '            this.createdComponent();\n' +
                '        });\n' +
                '\n' +
                '          return {\n' +
                '            isLoading,\n' +
                '            userId,\n' +
                '            user,\n' +
                '            currentUser,\n' +
                '            languages,\n' +
                '            integrations,\n' +
                '            currentIntegration,\n' +
                '            mediaItem,\n' +
                '            newPassword,\n' +
                '            newPasswordConfirm,\n' +
                '            isEmailUsed,\n' +
                '            isEmailAlreadyInUse,\n' +
                '            isUsernameUsed,\n' +
                '            isIntegrationsLoading,\n' +
                '            isSaveSuccessful,\n' +
                '            isModalLoading,\n' +
                '            showSecretAccessKey,\n' +
                '            showDeleteModal,\n' +
                '            skeletonItemAmount,\n' +
                '            confirmPasswordModal,\n' +
                '            timezoneOptions,\n' +
                '            mediaDefaultFolderId,\n' +
                '            showMediaModal,\n' +
                '            userService,\n' +
                '            loginService,\n' +
                '            mediaDefaultFolderService,\n' +
                '            userValidationService,\n' +
                '            integrationService,\n' +
                '            repositoryFactory,\n' +
                '            acl,\n' +
                '            identifier,\n' +
                '            checkUsername,\n' +
                '            loadMediaItem,\n' +
                '          };\n' +
                '        },\n' +
                'template,\n' +
                '\n' +
                '    compatConfig: Shopware.compatConfig,\n' +
                '\n' +
                '    \n' +
                '\n' +
                '    mixins: [\n' +
                "        Mixin.getByName('notification'),\n" +
                "        Mixin.getByName('salutation'),\n" +
                '    ],\n' +
                '\n' +
                '    shortcuts: {\n' +
                "        'SYSTEMKEY+S': 'onSave',\n" +
                "        ESCAPE: 'onCancel',\n" +
                '    },\n' +
                '\n' +
                '    \n' +
                '\n' +
                '    metaInfo() {\n' +
                '        return {\n' +
                '            title: this.$createTitle(this.identifier),\n' +
                '        };\n' +
                '    },\n' +
                '\n' +
                '    \n' +
                '\n' +
                '    \n' +
                '\n' +
                '    \n' +
                '\n' +
                '    \n' +
                '};\n'
        },
        {
            name: 'Invalid: Real world example 3',
            code: `
import template from './sw-settings-tax-detail.html.twig';
import './sw-settings-tax-detail.scss';

/**
 * @package checkout
 */

const { Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
        'systemConfigApiService',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    props: {
        taxId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            tax: {},
            isLoading: false,
            isSaveSuccessful: false,
            customFieldSets: null,
            defaultTaxRateId: null,
            changeDefaultTaxRate: false,
            formerDefaultTaxName: '',
            config: {},
            isDefault: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.tax.name || '';
        },

        taxRepository() {
            return this.repositoryFactory.create('tax');
        },

        ...mapPropertyErrors('tax', ['name', 'taxRate']),

        isNewTax() {
            return this.tax.isNew === 'function'
                ? this.tax.isNew()
                : false;
        },

        allowSave() {
            return this.isNewTax
                ? this.acl.can('tax.creator')
                : this.acl.can('tax.editor');
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: \`\${systemKey} + S\`,
                appearance: 'light',
            };
        },

        isShopwareDefaultTax() {
            return this.$te(\`global.tax-rates.\${this.tax.name}\`, 'en-GB');
        },

        label() {
            return this.isShopwareDefaultTax ? this.$tc(\`global.tax-rates.\${this.tax.name}\`) : this.tax.name;
        },

        showCustomFields() {
            return this.customFieldSets && this.customFieldSets.length > 0;
        },

        isDefaultTaxRate() {
            if (!this.defaultTaxRateId) {
                return false;
            }
            return this.taxId === this.defaultTaxRateId;
        },
    },

    watch: {
        taxId() {
            if (!this.taxId) {
                this.createdComponent();
            }
        },
        isDefaultTaxRate() {
            this.isDefault = this.isDefaultTaxRate;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (this.taxId) {
                this.taxRepository.get(this.taxId).then((tax) => {
                    this.tax = tax;
                    this.isLoading = false;
                });
                this.loadCustomFieldSets();
                this.reloadDefaultTaxRate();

                return;
            }

            this.tax = this.taxRepository.create();
            this.isLoading = false;
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('tax').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.taxRepository.save(this.tax).then(() => {
                this.isSaveSuccessful = true;
                if (!this.taxId) {
                    this.$router.push({ name: 'sw.settings.tax.detail', params: { id: this.tax.id } });
                }

                this.taxRepository.get(this.tax.id).then((updatedTax) => {
                    this.tax = updatedTax;
                }).then(() => {
                    return this.systemConfigApiService.saveValues(this.config).then(() => {
                        this.defaultTaxRateId = this.tax.id;
                        this.reloadDefaultTaxRate();
                        this.isLoading = false;
                    });
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-settings-tax.detail.messageSaveError'),
                });
                this.isLoading = false;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.tax.index' });
        },

        abortOnLanguageChange() {
            return this.taxRepository.hasChanges(this.tax);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.createdComponent();
        },

        changeName(name) {
            this.tax.name = name;
        },

        reloadDefaultTaxRate() {
            this.systemConfigApiService
                .getValues('core.tax')
                .then(response => {
                    this.defaultTaxRateId = response['core.tax.defaultTaxRate'] ?? null;
                })
                .then(() => {
                    if (this.defaultTaxRateId) {
                        this.taxRepository.get(this.defaultTaxRateId).then((tax) => {
                            this.formerDefaultTaxName = tax.name;
                        });
                    }
                })
                .catch(() => {
                    this.defaultTaxRateId = null;
                });
        },

        onChangeDefaultTaxRate() {
            const newDefaultTax = !this.isDefaultTaxRate ? this.taxId : '';

            this.$set(this.config, 'core.tax.defaultTaxRate', newDefaultTax);
            this.changeDefaultTaxRate = false;
        },
    },
};
`,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
'\n' +
"import { reactive, ref, inject, computed, onBeforeMount, watch } from 'vue';\n" +
"import template from './sw-settings-tax-detail.html.twig';\n" +
"import './sw-settings-tax-detail.scss';\n" +
'\n' +
'/**\n' +
' * @package checkout\n' +
' */\n' +
'\n' +
'const { Mixin } = Shopware;\n' +
'const { mapPropertyErrors } = Shopware.Component.getComponentHelper();\n' +
'\n' +
'export default {\n' +
'    setup(props) {\n' +
'          const tax = reactive({});\n' +
'      const isLoading = ref(false);\n' +
'      const isSaveSuccessful = ref(false);\n' +
'      const customFieldSets = ref(null);\n' +
'      const defaultTaxRateId = ref(null);\n' +
'      const changeDefaultTaxRate = ref(false);\n' +
"      const formerDefaultTaxName = ref('');\n" +
'      const config = reactive({});\n' +
'      const isDefault = ref(false);\n' +
'\n' +
'    /** TODO: Spread computed property is not fully supported yet. Original code:\n' +
"        mapPropertyErrors('tax', ['name', 'taxRate'])\n" +
'    */\n' +
"      const repositoryFactory = inject('repositoryFactory');\n" +
"      const acl = inject('acl');\n" +
"      const customFieldDataProviderService = inject('customFieldDataProviderService');\n" +
"      const systemConfigApiService = inject('systemConfigApiService');\n" +
"      const feature = inject('feature');\n" +
'          const identifier = computed(() => {\n' +
"            return tax.name || '';\n" +
'        });\n' +
'          const taxRepository = computed(() => {\n' +
"            return repositoryFactory.create('tax');\n" +
'        });\n' +
'          const isNewTax = computed(() => {\n' +
"            return tax.isNew === 'function'\n" +
'                ? tax.isNew()\n' +
'                : false;\n' +
'        });\n' +
'          const allowSave = computed(() => {\n' +
'            return isNewTax.value\n' +
"                ? acl.can('tax.creator')\n" +
"                : acl.can('tax.editor');\n" +
'        });\n' +
'          const tooltipSave = computed(() => {\n' +
'            if (!allowSave.value) {\n' +
'                return {\n' +
"                    message: this.$tc('sw-privileges.tooltip.warning'),\n" +
'                    disabled: allowSave.value,\n' +
'                    showOnDisabledElements: true,\n' +
'                };\n' +
'            }\n' +
'\n' +
'            const systemKey = this.$device.getSystemKey();\n' +
'\n' +
'            return {\n' +
'                message: `${systemKey} + S`,\n' +
"                appearance: 'light',\n" +
'            };\n' +
'        });\n' +
'          const isShopwareDefaultTax = computed(() => {\n' +
"            return this.$te(`global.tax-rates.${tax.name}`, 'en-GB');\n" +
'        });\n' +
'          const label = computed(() => {\n' +
'            return isShopwareDefaultTax.value ? this.$tc(`global.tax-rates.${tax.name}`) : tax.name;\n' +
'        });\n' +
'          const showCustomFields = computed(() => {\n' +
'            return customFieldSets.value && customFieldSets.value.length > 0;\n' +
'        });\n' +
'          const isDefaultTaxRate = computed(() => {\n' +
'            if (!defaultTaxRateId.value) {\n' +
'                return false;\n' +
'            }\n' +
'            return props.taxId === defaultTaxRateId.value;\n' +
'        });\n' +
'      const createdComponent = () => {\n' +
'            isLoading.value = true;\n' +
'            if (props.taxId) {\n' +
'                taxRepository.value.get(props.taxId).then((tax) => {\n' +
'                    Object.assign(tax, tax);\n' +
'                    isLoading.value = false;\n' +
'                });\n' +
'                loadCustomFieldSets();\n' +
'                reloadDefaultTaxRate();\n' +
'\n' +
'                return;\n' +
'            }\n' +
'\n' +
'            Object.assign(tax, taxRepository.value.create());\n' +
'            isLoading.value = false;\n' +
'        };\n' +
'      const loadCustomFieldSets = () => {\n' +
"            customFieldDataProviderService.getCustomFieldSets('tax').then((sets) => {\n" +
'                customFieldSets.value = sets;\n' +
'            });\n' +
'        };\n' +
'      const onSave = () => {\n' +
'            isSaveSuccessful.value = false;\n' +
'            isLoading.value = true;\n' +
'\n' +
'            return taxRepository.value.save(tax).then(() => {\n' +
'                isSaveSuccessful.value = true;\n' +
'                if (!props.taxId) {\n' +
"                    this.$router.push({ name: 'sw.settings.tax.detail', params: { id: tax.id } });\n" +
'                }\n' +
'\n' +
'                taxRepository.value.get(tax.id).then((updatedTax) => {\n' +
'                    Object.assign(tax, updatedTax);\n' +
'                }).then(() => {\n' +
'                    return systemConfigApiService.saveValues(config).then(() => {\n' +
'                        defaultTaxRateId.value = tax.id;\n' +
'                        reloadDefaultTaxRate();\n' +
'                        isLoading.value = false;\n' +
'                    });\n' +
'                });\n' +
'            }).catch(() => {\n' +
'                this.createNotificationError({\n' +
"                    message: this.$tc('sw-settings-tax.detail.messageSaveError'),\n" +
'                });\n' +
'                isLoading.value = false;\n' +
'            });\n' +
'        };\n' +
'      const onCancel = () => {\n' +
"            this.$router.push({ name: 'sw.settings.tax.index' });\n" +
'        };\n' +
'      const abortOnLanguageChange = () => {\n' +
'            return taxRepository.value.hasChanges(tax);\n' +
'        };\n' +
'      const saveOnLanguageChange = () => {\n' +
'            return onSave();\n' +
'        };\n' +
'      const onChangeLanguage = (languageId) => {\n' +
"            Shopware.State.commit('context/setApiLanguageId', languageId);\n" +
'            createdComponent();\n' +
'        };\n' +
'      const changeName = (name) => {\n' +
'            tax.name = name;\n' +
'        };\n' +
'      const reloadDefaultTaxRate = () => {\n' +
'            systemConfigApiService\n' +
"                .getValues('core.tax')\n" +
'                .then(response => {\n' +
"                    defaultTaxRateId.value = response['core.tax.defaultTaxRate'] ?? null;\n" +
'                })\n' +
'                .then(() => {\n' +
'                    if (defaultTaxRateId.value) {\n' +
'                        taxRepository.value.get(defaultTaxRateId.value).then((tax) => {\n' +
'                            formerDefaultTaxName.value = tax.name;\n' +
'                        });\n' +
'                    }\n' +
'                })\n' +
'                .catch(() => {\n' +
'                    defaultTaxRateId.value = null;\n' +
'                });\n' +
'        };\n' +
'      const onChangeDefaultTaxRate = () => {\n' +
"            const newDefaultTax = !isDefaultTaxRate.value ? props.taxId : '';\n" +
'\n' +
"            this.$set(config, 'core.tax.defaultTaxRate', newDefaultTax);\n" +
'            changeDefaultTaxRate.value = false;\n' +
'        };\n' +
'      onBeforeMount(() => {\n' +
'        createdComponent();\n' +
'    });\n' +
'      watch(props.taxId, () => {\n' +
'            if (!props.taxId) {\n' +
'                createdComponent();\n' +
'            }\n' +
'        });\n' +
'      watch(isDefaultTaxRate, () => {\n' +
'            isDefault.value = isDefaultTaxRate.value;\n' +
'        });\n' +
'\n' +
'          return {\n' +
'            tax,\n' +
'            isLoading,\n' +
'            isSaveSuccessful,\n' +
'            customFieldSets,\n' +
'            defaultTaxRateId,\n' +
'            changeDefaultTaxRate,\n' +
'            formerDefaultTaxName,\n' +
'            config,\n' +
'            isDefault,\n' +
'            repositoryFactory,\n' +
'            acl,\n' +
'            customFieldDataProviderService,\n' +
'            systemConfigApiService,\n' +
'            feature,\n' +
'            identifier,\n' +
'            taxRepository,\n' +
'            isNewTax,\n' +
'            allowSave,\n' +
'            tooltipSave,\n' +
'            isShopwareDefaultTax,\n' +
'            label,\n' +
'            showCustomFields,\n' +
'            isDefaultTaxRate,\n' +
'            createdComponent,\n' +
'            loadCustomFieldSets,\n' +
'            onSave,\n' +
'            onCancel,\n' +
'            abortOnLanguageChange,\n' +
'            saveOnLanguageChange,\n' +
'            onChangeLanguage,\n' +
'            changeName,\n' +
'            reloadDefaultTaxRate,\n' +
'            onChangeDefaultTaxRate,\n' +
'          };\n' +
'        },\n' +
'template,\n' +
'\n' +
'    compatConfig: Shopware.compatConfig,\n' +
'\n' +
'    \n' +
'\n' +
'    mixins: [\n' +
"        Mixin.getByName('notification'),\n" +
'    ],\n' +
'\n' +
'    shortcuts: {\n' +
"        'SYSTEMKEY+S': {\n" +
'            active() {\n' +
'                return this.allowSave;\n' +
'            },\n' +
"            method: 'onSave',\n" +
'        },\n' +
"        ESCAPE: 'onCancel',\n" +
'    },\n' +
'\n' +
'    props: {\n' +
'        taxId: {\n' +
'            type: String,\n' +
'            required: false,\n' +
'            default: null,\n' +
'        },\n' +
'    },\n' +
'\n' +
'    \n' +
'\n' +
'    metaInfo() {\n' +
'        return {\n' +
'            title: this.$createTitle(this.identifier),\n' +
'        };\n' +
'    },\n' +
'\n' +
'    \n' +
'\n' +
'    \n' +
'\n' +
'    \n' +
'\n' +
'    \n' +
'};\n'
        },
        {
            name: 'Invalid: Real world example 4',
            code: `
import Sanitizer from 'src/core/helper/sanitizer.helper';
import template from './sw-settings-snippet-list.html.twig';
import './sw-settings-snippet-list.scss';

const { Mixin, Data: { Criteria } } = Shopware;

export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'snippetSetService',
        'snippetService',
        'userService',
        'repositoryFactory',
        'acl',
        'userConfigService',
    ],

    mixins: [
        Mixin.getByName('sw-settings-list'),
    ],

    data() {
        return {
            entityName: 'snippet',
            sortBy: 'id',
            sortDirection: 'ASC',
            metaId: '',
            currentAuthor: '',
            snippetSets: null,
            hasResetableItems: true,
            showOnlyEdited: false,
            showOnlyAdded: false,
            emptySnippets: false,
            grid: [],
            resetItems: [],
            filterItems: [],
            authorFilters: [],
            appliedFilter: [],
            appliedAuthors: [],
            emptyIcon: this.$route.meta.$module.icon,
            skeletonItemAmount: 25,
            filterSettings: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        filter() {
            const filter = {};
            if (this.showOnlyEdited) {
                filter.edited = true;
            }
            if (this.showOnlyAdded) {
                filter.added = true;
            }
            if (this.emptySnippets) {
                filter.empty = true;
            }
            if (this.term) {
                filter.term = this.term;
            }
            if (this.appliedFilter.length > 0) {
                filter.namespace = this.appliedFilter;
            }
            if (this.appliedAuthors.length > 0) {
                filter.author = this.appliedAuthors;
            }

            return filter;
        },

        contextMenuEditSnippet() {
            return this.acl.can('snippet.editor') ?
                this.$tc('global.default.edit') :
                this.$tc('global.default.view');
        },

        hasActiveFilters() {
            if (!this.filterSettings) {
                return false;
            }

            return Object.values(this.filterSettings).some((value) => value === true);
        },

        activeFilters() {
            let filter = {};

            if (!this.hasActiveFilters) {
                return filter;
            }

            if (this.filterSettings.editedSnippets) {
                filter = { ...filter, edited: true };
            }
            if (this.filterSettings.addedSnippets) {
                filter = { ...filter, added: true };
            }
            if (this.filterSettings.emptySnippets) {
                filter = { ...filter, empty: true };
            }

            filter = { ...filter, author: [] };
            this.authorFilters.forEach((item) => {
                if (this.filterSettings[item] === true) {
                    filter.author.push(item);
                }
            });

            filter = { ...filter, namespace: [] };
            this.filterItems.forEach((item) => {
                if (this.filterSettings[item] === true) {
                    filter.namespace.push(item);
                }
            });

            return filter;
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.beforeDestroyComponent();
    },

    methods: {
        async createdComponent() {
            this.addEventListeners();

            this.snippetSetRepository.search(this.snippetSetCriteria)
                .then((sets) => {
                    this.snippetSets = sets;
                });

            this.userService.getUser().then((response) => {
                this.currentAuthor = \`user/\${response.data.username}\`;
            });

            const filterItems = await this.snippetService.getFilter();
            this.filterItems = filterItems.data;

            const authorFilters = await this.snippetSetService.getAuthors();
            this.authorFilters = authorFilters.data;

            await this.getFilterSettings();

            if (this.hasActiveFilters) {
                this.initializeSnippetSet(this.activeFilters);
            }
        },

        beforeDestroyComponent() {
            this.saveUserConfig();
            this.removeEventListeners();
        },

        createFilterSettings() {
            const authorFilters = this.authorFilters.reduce((acc, item) => ({ ...acc, [item]: false }), {});
            const moreFilters = this.filterItems.reduce((acc, item) => ({ ...acc, [item]: false }), {});

            return {
                emptySnippets: false,
                editedSnippets: false,
                addedSnippets: false,
                ...authorFilters,
                ...moreFilters,
            };
        },

        getList() {
            if (this.hasActiveFilters) {
                this.initializeSnippetSet(this.activeFilters);
            } else {
                this.initializeSnippetSet();
            }
        },

        getColumns() {
            const columns = [{
                property: 'id',
                label: 'sw-settings-snippet.list.columnKey',
                inlineEdit: true,
                allowResize: true,
                rawData: true,
                primary: true,
            }];

            if (this.snippetSets) {
                this.snippetSets.forEach((item) => {
                    columns.push({
                        property: item.id,
                        label: item.name,
                        allowResize: true,
                        inlineEdit: 'string',
                        rawData: true,
                    });
                });
            }
            return columns;
        },

        initializeSnippetSet(filter = this.filter) {
            if (!this.$route.query.ids) {
                this.backRoutingError();
                return;
            }

            this.isLoading = true;

            const sort = {
                sortBy: this.sortBy,
                sortDirection: this.sortDirection,
            };

            this.snippetSetService.getCustomList(this.page, this.limit, filter, sort).then((response) => {
                this.metaId = this.queryIds[0];
                this.total = response.total;
                this.grid = this.prepareGrid(response.data);
                this.isLoading = false;
            });
        },

        prepareGrid(grid) {
            function prepareContent(items) {
                const content = items.reduce((acc, item) => {
                    item.resetTo = item.value;
                    acc[item.setId] = item;
                    acc.isCustomSnippet = item.author.includes('user/');
                    return acc;
                }, {});
                content.id = items[0].translationKey;

                return content;
            }

            return Object.values(grid).reduce((accumulator, items) => {
                accumulator.push(prepareContent(items));
                return accumulator;
            }, []);
        },

        onEdit(snippet) {
            if (snippet?.id) {
                this.$router.push({
                    name: 'sw.settings.snippet.detail',
                    params: {
                        id: snippet.id,
                    },
                });
            }
        },
    },
};
`,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
'\n' +
"import { ref, inject, computed, onBeforeUnmount, onBeforeMount } from 'vue';\n" +
"import Sanitizer from 'src/core/helper/sanitizer.helper';\n" +
"import template from './sw-settings-snippet-list.html.twig';\n" +
"import './sw-settings-snippet-list.scss';\n" +
'\n' +
'const { Mixin, Data: { Criteria } } = Shopware;\n' +
'\n' +
'export default {\n' +
'    setup() {\n' +
"          const entityName = ref('snippet');\n" +
"      const sortBy = ref('id');\n" +
"      const sortDirection = ref('ASC');\n" +
"      const metaId = ref('');\n" +
"      const currentAuthor = ref('');\n" +
'      const snippetSets = ref(null);\n' +
'      const hasResetableItems = ref(true);\n' +
'      const showOnlyEdited = ref(false);\n' +
'      const showOnlyAdded = ref(false);\n' +
'      const emptySnippets = ref(false);\n' +
'      const grid = ref(undefined);\n' +
'      const resetItems = ref(undefined);\n' +
'      const filterItems = ref(undefined);\n' +
'      const authorFilters = ref(undefined);\n' +
'      const appliedFilter = ref(undefined);\n' +
'      const appliedAuthors = ref(undefined);\n' +
'      const emptyIcon = ref(undefined);\n' +
'      const skeletonItemAmount = ref(25);\n' +
'      const filterSettings = ref(null);\n' +
"      const snippetSetService = inject('snippetSetService');\n" +
"      const snippetService = inject('snippetService');\n" +
"      const userService = inject('userService');\n" +
"      const repositoryFactory = inject('repositoryFactory');\n" +
"      const acl = inject('acl');\n" +
"      const userConfigService = inject('userConfigService');\n" +
'          const filter = computed(() => {\n' +
'            const filter = {};\n' +
'            if (showOnlyEdited.value) {\n' +
'                filter.edited = true;\n' +
'            }\n' +
'            if (showOnlyAdded.value) {\n' +
'                filter.added = true;\n' +
'            }\n' +
'            if (emptySnippets.value) {\n' +
'                filter.empty = true;\n' +
'            }\n' +
'            if (this.term) {\n' +
'                filter.term = this.term;\n' +
'            }\n' +
'            if (appliedFilter.value.length > 0) {\n' +
'                filter.namespace = appliedFilter.value;\n' +
'            }\n' +
'            if (appliedAuthors.value.length > 0) {\n' +
'                filter.author = appliedAuthors.value;\n' +
'            }\n' +
'\n' +
'            return filter;\n' +
'        });\n' +
'          const contextMenuEditSnippet = computed(() => {\n' +
"            return acl.can('snippet.editor') ?\n" +
"                this.$tc('global.default.edit') :\n" +
"                this.$tc('global.default.view');\n" +
'        });\n' +
'          const hasActiveFilters = computed(() => {\n' +
'            if (!filterSettings.value) {\n' +
'                return false;\n' +
'            }\n' +
'\n' +
'            return Object.values(filterSettings.value).some((value) => value === true);\n' +
'        });\n' +
'          const activeFilters = computed(() => {\n' +
'            let filter = {};\n' +
'\n' +
'            if (!hasActiveFilters.value) {\n' +
'                return filter;\n' +
'            }\n' +
'\n' +
'            if (filterSettings.value.editedSnippets) {\n' +
'                filter = { ...filter, edited: true };\n' +
'            }\n' +
'            if (filterSettings.value.addedSnippets) {\n' +
'                filter = { ...filter, added: true };\n' +
'            }\n' +
'            if (filterSettings.value.emptySnippets) {\n' +
'                filter = { ...filter, empty: true };\n' +
'            }\n' +
'\n' +
'            filter = { ...filter, author: [] };\n' +
'            authorFilters.value.forEach((item) => {\n' +
'                if (filterSettings.value[item] === true) {\n' +
'                    filter.author.push(item);\n' +
'                }\n' +
'            });\n' +
'\n' +
'            filter = { ...filter, namespace: [] };\n' +
'            filterItems.value.forEach((item) => {\n' +
'                if (filterSettings.value[item] === true) {\n' +
'                    filter.namespace.push(item);\n' +
'                }\n' +
'            });\n' +
'\n' +
'            return filter;\n' +
'        });\n' +
'      const createdComponent = async () => {\n' +
'            this.addEventListeners();\n' +
'\n' +
'            this.snippetSetRepository.search(this.snippetSetCriteria)\n' +
'                .then((sets) => {\n' +
'                    snippetSets.value = sets;\n' +
'                });\n' +
'\n' +
'            userService.getUser().then((response) => {\n' +
'                currentAuthor.value = `user/${response.data.username}`;\n' +
'            });\n' +
'\n' +
'            const filterItems = await snippetService.getFilter();\n' +
'            filterItems.value = filterItems.data;\n' +
'\n' +
'            const authorFilters = await snippetSetService.getAuthors();\n' +
'            authorFilters.value = authorFilters.data;\n' +
'\n' +
'            await this.getFilterSettings();\n' +
'\n' +
'            if (hasActiveFilters.value) {\n' +
'                initializeSnippetSet(activeFilters.value);\n' +
'            }\n' +
'        };\n' +
'      const beforeDestroyComponent = () => {\n' +
'            this.saveUserConfig();\n' +
'            this.removeEventListeners();\n' +
'        };\n' +
'      const createFilterSettings = () => {\n' +
'            const authorFilters = authorFilters.value.reduce((acc, item) => ({ ...acc, [item]: false }), {});\n' +
'            const moreFilters = filterItems.value.reduce((acc, item) => ({ ...acc, [item]: false }), {});\n' +
'\n' +
'            return {\n' +
'                emptySnippets: false,\n' +
'                editedSnippets: false,\n' +
'                addedSnippets: false,\n' +
'                ...authorFilters,\n' +
'                ...moreFilters,\n' +
'            };\n' +
'        };\n' +
'      const getList = () => {\n' +
'            if (hasActiveFilters.value) {\n' +
'                initializeSnippetSet(activeFilters.value);\n' +
'            } else {\n' +
'                initializeSnippetSet();\n' +
'            }\n' +
'        };\n' +
'      const getColumns = () => {\n' +
'            const columns = [{\n' +
"                property: 'id',\n" +
"                label: 'sw-settings-snippet.list.columnKey',\n" +
'                inlineEdit: true,\n' +
'                allowResize: true,\n' +
'                rawData: true,\n' +
'                primary: true,\n' +
'            }];\n' +
'\n' +
'            if (snippetSets.value) {\n' +
'                snippetSets.value.forEach((item) => {\n' +
'                    columns.push({\n' +
'                        property: item.id,\n' +
'                        label: item.name,\n' +
'                        allowResize: true,\n' +
"                        inlineEdit: 'string',\n" +
'                        rawData: true,\n' +
'                    });\n' +
'                });\n' +
'            }\n' +
'            return columns;\n' +
'        };\n' +
'      const initializeSnippetSet = (filter = filter.value) => {\n' +
'            if (!this.$route.query.ids) {\n' +
'                this.backRoutingError();\n' +
'                return;\n' +
'            }\n' +
'\n' +
'            this.isLoading = true;\n' +
'\n' +
'            const sort = {\n' +
'                sortBy: sortBy.value,\n' +
'                sortDirection: sortDirection.value,\n' +
'            };\n' +
'\n' +
'            snippetSetService.getCustomList(this.page, this.limit, filter, sort).then((response) => {\n' +
'                metaId.value = this.queryIds[0];\n' +
'                this.total = response.total;\n' +
'                grid.value = prepareGrid(response.data);\n' +
'                this.isLoading = false;\n' +
'            });\n' +
'        };\n' +
'      const prepareGrid = (grid) => {\n' +
'            function prepareContent(items) {\n' +
'                const content = items.reduce((acc, item) => {\n' +
'                    item.resetTo = item.value;\n' +
'                    acc[item.setId] = item;\n' +
"                    acc.isCustomSnippet = item.author.includes('user/');\n" +
'                    return acc;\n' +
'                }, {});\n' +
'                content.id = items[0].translationKey;\n' +
'\n' +
'                return content;\n' +
'            }\n' +
'\n' +
'            return Object.values(grid).reduce((accumulator, items) => {\n' +
'                accumulator.push(prepareContent(items));\n' +
'                return accumulator;\n' +
'            }, []);\n' +
'        };\n' +
'      const onEdit = (snippet) => {\n' +
'            if (snippet?.id) {\n' +
'                this.$router.push({\n' +
"                    name: 'sw.settings.snippet.detail',\n" +
'                    params: {\n' +
'                        id: snippet.id,\n' +
'                    },\n' +
'                });\n' +
'            }\n' +
'        };\n' +
'      onBeforeUnmount(() => {\n' +
'        beforeDestroyComponent();\n' +
'    });\n' +
'      onBeforeMount(() => {\n' +
'        createdComponent();\n' +
'    });\n' +
'\n' +
'          return {\n' +
'            entityName,\n' +
'            sortBy,\n' +
'            sortDirection,\n' +
'            metaId,\n' +
'            currentAuthor,\n' +
'            snippetSets,\n' +
'            hasResetableItems,\n' +
'            showOnlyEdited,\n' +
'            showOnlyAdded,\n' +
'            emptySnippets,\n' +
'            grid,\n' +
'            resetItems,\n' +
'            filterItems,\n' +
'            authorFilters,\n' +
'            appliedFilter,\n' +
'            appliedAuthors,\n' +
'            emptyIcon,\n' +
'            skeletonItemAmount,\n' +
'            filterSettings,\n' +
'            snippetSetService,\n' +
'            snippetService,\n' +
'            userService,\n' +
'            repositoryFactory,\n' +
'            acl,\n' +
'            userConfigService,\n' +
'            filter,\n' +
'            contextMenuEditSnippet,\n' +
'            hasActiveFilters,\n' +
'            activeFilters,\n' +
'            createdComponent,\n' +
'            beforeDestroyComponent,\n' +
'            createFilterSettings,\n' +
'            getList,\n' +
'            getColumns,\n' +
'            initializeSnippetSet,\n' +
'            prepareGrid,\n' +
'            onEdit,\n' +
'          };\n' +
'        },\n' +
'template,\n' +
'\n' +
'    compatConfig: Shopware.compatConfig,\n' +
'\n' +
'    \n' +
'\n' +
'    mixins: [\n' +
"        Mixin.getByName('sw-settings-list'),\n" +
'    ],\n' +
'\n' +
'    \n' +
'\n' +
'    metaInfo() {\n' +
'        return {\n' +
'            title: this.$createTitle(this.identifier),\n' +
'        };\n' +
'    },\n' +
'\n' +
'    \n' +
'\n' +
'    \n' +
'\n' +
'    \n' +
'\n' +
'    \n' +
'};\n'
        },
        {
            name: 'Invalid: Real world example 5',
            code: `
            import template from './sw-sales-channel-detail-domains.html.twig';
import './sw-sales-channel-detail-domains.scss';

const { Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;
const { ShopwareError } = Shopware.Classes;

export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        salesChannel: {
            required: true,
        },
    },

    data() {
        return {
            currentDomain: null,
            currentDomainBackup: {
                url: null,
                language: null,
                languageId: null,
                currency: null,
                currencyId: null,
                snippetSet: null,
                snippetSetId: null,
            },
            isLoadingDomains: false,
            deleteDomain: null,
            sortBy: 'url',
            sortDirection: 'ASC',
            error: null,
        };
    },

    computed: {
        domainRepository() {
            return this.repositoryFactory.create(
                this.salesChannel.domains.entity,
                this.salesChannel.domains.source,
            );
        },

        currentDomainModalTitle() {
            if (this.currentDomain?.isNew()) {
                return this.$t('sw-sales-channel.detail.titleCreateDomain');
            }

            return this.$t('sw-sales-channel.detail.titleEditDomain', 0, {
                name: this.unicodeUriFilter(this.currentDomainBackup.url),
            });
        },
    },

    methods: {
        async domainExistsInDatabase(url) {
            const globalDomainRepository = this.repositoryFactory.create(this.salesChannel.domains.entity);
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('url', url));

            const items = await globalDomainRepository.search(criteria);

            if (items.total === 0) {
                return false;
            }

            return items.first().salesChannelId !== this.salesChannel.id;
        },

        setCurrentDomainBackup(domain) {
            this.currentDomainBackup = {
                url: domain.url,
                language: domain.language,
                languageId: domain.languageId,
                currency: domain.currency,
                currencyId: domain.currencyId,
                snippetSet: domain.snippetSet,
                snippetSetId: domain.snippetSetId,
            };
        },

        resetCurrentDomainToBackup() {
            this.currentDomain.url = this.currentDomainBackup.url;
            this.currentDomain.language = this.currentDomainBackup.language;
            this.currentDomain.languageId = this.currentDomainBackup.languageId;
            this.currentDomain.currency = this.currentDomainBackup.currency;
            this.currentDomain.currencyId = this.currentDomainBackup.currencyId;
            this.currentDomain.snippetSet = this.currentDomainBackup.snippetSet;
            this.currentDomain.snippetSetId = this.currentDomainBackup.snippetSetId;
        },
    },
};
            `,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
'\n' +
"            import { ref, reactive, inject, computed } from 'vue';\n" +
"import template from './sw-sales-channel-detail-domains.html.twig';\n" +
"import './sw-sales-channel-detail-domains.scss';\n" +
'\n' +
'const { Mixin, Context } = Shopware;\n' +
'const { Criteria } = Shopware.Data;\n' +
'const { ShopwareError } = Shopware.Classes;\n' +
'\n' +
'export default {\n' +
'    setup(props) {\n' +
'          const currentDomain = ref(null);\n' +
'      const currentDomainBackup = reactive({\n' +
'                url: null,\n' +
'                language: null,\n' +
'                languageId: null,\n' +
'                currency: null,\n' +
'                currencyId: null,\n' +
'                snippetSet: null,\n' +
'                snippetSetId: null,\n' +
'            });\n' +
'      const isLoadingDomains = ref(false);\n' +
'      const deleteDomain = ref(null);\n' +
"      const sortBy = ref('url');\n" +
"      const sortDirection = ref('ASC');\n" +
'      const error = ref(null);\n' +
"      const repositoryFactory = inject('repositoryFactory');\n" +
'          const domainRepository = computed(() => {\n' +
'            return repositoryFactory.create(\n' +
'                props.salesChannel.domains.entity,\n' +
'                props.salesChannel.domains.source,\n' +
'            );\n' +
'        });\n' +
'          const currentDomainModalTitle = computed(() => {\n' +
'            if (currentDomain.value?.isNew()) {\n' +
"                return this.$t('sw-sales-channel.detail.titleCreateDomain');\n" +
'            }\n' +
'\n' +
"            return this.$t('sw-sales-channel.detail.titleEditDomain', 0, {\n" +
'                name: this.unicodeUriFilter(currentDomainBackup.url),\n' +
'            });\n' +
'        });\n' +
'      const domainExistsInDatabase = async (url) => {\n' +
'            const globalDomainRepository = repositoryFactory.create(props.salesChannel.domains.entity);\n' +
'            const criteria = new Criteria(1, 25);\n' +
"            criteria.addFilter(Criteria.equals('url', url));\n" +
'\n' +
'            const items = await globalDomainRepository.search(criteria);\n' +
'\n' +
'            if (items.total === 0) {\n' +
'                return false;\n' +
'            }\n' +
'\n' +
'            return items.first().salesChannelId !== props.salesChannel.id;\n' +
'        };\n' +
'      const setCurrentDomainBackup = (domain) => {\n' +
'            Object.assign(currentDomainBackup, {\n' +
'                url: domain.url,\n' +
'                language: domain.language,\n' +
'                languageId: domain.languageId,\n' +
'                currency: domain.currency,\n' +
'                currencyId: domain.currencyId,\n' +
'                snippetSet: domain.snippetSet,\n' +
'                snippetSetId: domain.snippetSetId,\n' +
'            })\n' +
';\n' +
'        };\n' +
'      const resetCurrentDomainToBackup = () => {\n' +
'            currentDomain.value.url = currentDomainBackup.url;\n' +
'            currentDomain.value.language = currentDomainBackup.language;\n' +
'            currentDomain.value.languageId = currentDomainBackup.languageId;\n' +
'            currentDomain.value.currency = currentDomainBackup.currency;\n' +
'            currentDomain.value.currencyId = currentDomainBackup.currencyId;\n' +
'            currentDomain.value.snippetSet = currentDomainBackup.snippetSet;\n' +
'            currentDomain.value.snippetSetId = currentDomainBackup.snippetSetId;\n' +
'        };\n' +
'\n' +
'          return {\n' +
'            currentDomain,\n' +
'            currentDomainBackup,\n' +
'            isLoadingDomains,\n' +
'            deleteDomain,\n' +
'            sortBy,\n' +
'            sortDirection,\n' +
'            error,\n' +
'            repositoryFactory,\n' +
'            domainRepository,\n' +
'            currentDomainModalTitle,\n' +
'            domainExistsInDatabase,\n' +
'            setCurrentDomainBackup,\n' +
'            resetCurrentDomainToBackup,\n' +
'          };\n' +
'        },\n' +
'template,\n' +
'\n' +
'    compatConfig: Shopware.compatConfig,\n' +
'\n' +
'    \n' +
'\n' +
'    mixins: [\n' +
"        Mixin.getByName('notification'),\n" +
'    ],\n' +
'\n' +
'    props: {\n' +
'        salesChannel: {\n' +
'            required: true,\n' +
'        },\n' +
'    },\n' +
'\n' +
'    \n' +
'\n' +
'    \n' +
'\n' +
'    \n' +
'};\n' +
'            ',
        },
        {
            name: 'Invalid: Real world example 6',
            code: `
            export default {
                data() {
                    return {
                        numberRangeId: undefined,
                        numberRange: {},
                    };
                },
                computed: {
                    numberRangeRepository() {
                        return this.repositoryFactory.create('number_range');
                    },
                    numberRangeCriteria() {
                        const criteria = new Criteria(1, 25);

                        criteria.addAssociation('type');
                        criteria.addAssociation('numberRangeSalesChannels');

                        return criteria;
                    },
                },
                methods: {
                    getState() {
                        if (!this.numberRange.type.technicalName) {
                            return Promise.resolve();
                        }

                        return this.numberRangeService.previewPattern(
                            this.numberRange.type.technicalName,
                            '{n}',
                            0,
                        ).then((response) => {
                            if (response.number > 1) {
                                this.state = response.number - 1;
                                return Promise.resolve();
                            }

                            this.state = this.numberRange.start;
                            return Promise.resolve();
                        });
                    },
                    async loadEntityData() {
                        this.numberRange = await this.numberRangeRepository.get(
                            this.numberRangeId,
                            Shopware.Context.api,
                            this.numberRangeCriteria,
                        );

                        this.getState();
                        this.splitPattern();
                        await this.loadSalesChannels();
                    },
                },
            }
`,
            errors: [
                { message: 'Add all missing Vue composition API imports at the beginning of the file' },
                { message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.' },
            ],
            output: '' +
'\n' +
"            import { ref, reactive, computed } from 'vue';\n" +
'export default {\n' +
'                setup() {\n' +
"          const numberRangeId = ref(undefined);\n" +
'      const numberRange = reactive({});\n' +
'          const numberRangeRepository = computed(() => {\n' +
"                        return this.repositoryFactory.create('number_range');\n" +
'                    });\n' +
'          const numberRangeCriteria = computed(() => {\n' +
'                        const criteria = new Criteria(1, 25);\n' +
'\n' +
'                        criteria.addAssociation(\'type\');\n' +
'                        criteria.addAssociation(\'numberRangeSalesChannels\');\n' +
'\n' +
'                        return criteria;\n' +
'                    });\n' +
'      const getState = () => {\n' +
'                        if (!numberRange.type.technicalName) {\n' +
'                            return Promise.resolve();\n' +
'                        }\n' +
'\n' +
'                        return this.numberRangeService.previewPattern(\n' +
'                            numberRange.type.technicalName,\n' +
'                            \'{n}\',\n' +
'                            0,\n' +
'                        ).then((response) => {\n' +
'                            if (response.number > 1) {\n' +
'                                this.state = response.number - 1;\n' +
'                                return Promise.resolve();\n' +
'                            }\n' +
'\n' +
'                            this.state = numberRange.start;\n' +
'                            return Promise.resolve();\n' +
'                        });\n' +
'                    };\n' +
'      const loadEntityData = async () => {\n' +
'                        Object.assign(numberRange, await numberRangeRepository.value.get(\n' +
'                            numberRangeId.value,\n' +
'                            Shopware.Context.api,\n' +
'                            numberRangeCriteria.value,\n' +
'                        ))\n' +
';\n' +
'\n' +
'                        getState();\n' +
'                        this.splitPattern();\n' +
'                        await this.loadSalesChannels();\n' +
'                    };\n' +
'\n' +
'          return {\n' +
'            numberRangeId,\n' +
'            numberRange,\n' +
'            numberRangeRepository,\n' +
'            numberRangeCriteria,\n' +
'            getState,\n' +
'            loadEntityData,\n' +
'          };\n' +
'        },\n' +
'\n' +
'                \n' +
'                \n' +
'            }\n',
        },
    ]
});
