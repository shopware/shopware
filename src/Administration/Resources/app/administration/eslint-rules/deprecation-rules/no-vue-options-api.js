module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Convert Vue 2 Options API components to Vue 3 Composition API',
            category: 'Vue 3 Migration',
            recommended: false
        },
        fixable: 'code', // This rule is fixable
        schema: [
            {
                enum: ['disableFix', 'enableFix'],
            }
        ],
    },
    create(context) {
        const vueProperties = [
            'data',
            'props',
            'computed',
            'methods',
            'watch',
            'mounted',
            'updated',
            'unmounted',
            'beforeMount',
            'beforeUpdate',
            'beforeUnmount',
            'emits',
            // Legacy Vue 2 lifecycle hooks
            'created',
            'beforeCreate',
            'beforeDestroy',
            'destroyed',
        ];

        // Array to store all imports that are needed to be added in the end
        let neededImports = [];

        function isComponentDefinition(node) {
            const parent = node.parent;

            // default export component
            if (parent.type === 'ExportDefaultDeclaration') {
                // Check if the parent node contains at least one property from the vueProperties array
                return node.properties.some(prop => vueProperties.includes(prop.key.name));
            }

            // registered component, extend component or mixin
            if (parent.type === 'CallExpression' &&
                parent.callee.type === 'MemberExpression' &&
                (parent.callee.property.name === 'register' ||
                    parent.callee.property.name === 'extend')) {
                const callExpression = parent;

                // Component.register() or Component.extend()
                if (callExpression.callee.object.name === 'Component') {
                    return true;
                }

                // Shopware.Component.register() or Shopware.Component.extend()
                if (parent.callee.object.type === 'MemberExpression' &&
                    parent.callee.object.object.name === 'Shopware' &&
                    parent.callee.object.property.name === 'Component'
                ) {
                    return true;
                }

                // Mixin.register()
                if (callExpression.callee.object.name === 'Mixin' &&
                    callExpression.callee.property.name === 'register') {
                    return true;
                }

                // Shopware.Mixin.register()
                if (parent.callee.object.type === 'MemberExpression' &&
                    parent.callee.object.object.name === 'Shopware' &&
                    parent.callee.object.property.name === 'Mixin') {
                    return true;
                }
            }

            return false;
        }

        return {
            ObjectExpression(node) {
                const isComponent = isComponentDefinition(node);

                if (!isComponent) return;

                const componentObjectExpression = node;
                const componentProperties = componentObjectExpression.properties;

                // Skip if no component properties are found
                if (!componentProperties) return;

                // Check if the component contains a 'setup' function
                const setupNode = componentProperties.find(prop => prop.key?.name === 'setup');

                // If the component already contains a 'setup' function, skip the rule
                if (setupNode) return;

                // Props: No change needed, just ensure they're passed to `setup()`.
                const propsNode = componentProperties.find(prop => prop.key?.name === 'props');

                // Data: Convert to ref
                const dataNode = componentProperties.find(prop => prop.key?.name === 'data');

                // Computed: Convert to `computed()`
                const computedNode = componentProperties.find(prop => prop.key?.name === 'computed');

                // Methods: Convert to functions within setup
                const methodsNode = componentProperties.find(prop => prop.key?.name === 'methods');

                // Watch: Convert to `watch()`
                const watchNode = componentProperties.find(prop => prop.key?.name === 'watch');

                // Mounted: Convert to `onMounted()`
                const mountedNode = componentProperties.find(prop => prop.key?.name === 'mounted');

                // Updated: Convert to `onUpdated()`
                const updatedNode = componentProperties.find(prop => prop.key?.name === 'updated');

                // Unmounted: Convert to `onUnmounted()`
                const unmountedNode = componentProperties.find(prop => prop.key?.name === 'unmounted');

                // BeforeMount: Convert to `onBeforeMount()`
                const beforeMountNode = componentProperties.find(prop => prop.key?.name === 'beforeMount');

                // BeforeUpdate: Convert to `onBeforeUpdate()`
                const beforeUpdateNode = componentProperties.find(prop => prop.key?.name === 'beforeUpdate');

                // BeforeUnmount: Convert to `onBeforeUnmount()`
                const beforeUnmountNode = componentProperties.find(prop => prop.key?.name === 'beforeUnmount');

                // Legacy Vue 2 lifecycle hooks

                // Created: Convert to `onBeforeMount()`
                const createdNode = componentProperties.find(prop => prop.key?.name === 'created');

                // BeforeCreate: Convert to `onBeforeMount()`
                const beforeCreateNode = componentProperties.find(prop => prop.key?.name === 'beforeCreate');

                // BeforeDestroy: Convert to `onBeforeUnmount()`
                const beforeDestroyNode = componentProperties.find(prop => prop.key?.name === 'beforeDestroy');

                // Destroyed: Convert to `onUnmounted()`
                const destroyedNode = componentProperties.find(prop => prop.key?.name === 'destroyed');

                // Inject: Convert to `inject()`
                const injectNode = componentProperties.find(prop => prop.key?.name === 'inject');

                context.report({
                    node,
                    message: 'Vue 2 Options API shouldn\'t be used anymore. Convert to Vue 3 Composition API.',
                    *fix(fixer) {
                        if (context.options.includes('disableFix')) return;

                        // Array to store all fixes
                        let fixes = [];

                        // Handle `data() { return {...}}` conversion to `const ref = ...`
                        if (dataNode) {
                            const dataProperties = dataNode.value.body.body[0].argument.properties;

                            // Loop through each data property and convert to ref/reactive
                            dataProperties.forEach(dataProp => {
                                const isObject = dataProp.value.type === 'ObjectExpression';

                                if (isObject) {
                                    // Push reactive conversion to fixes array
                                    fixes.push({
                                        type: 'dataReactive',
                                        key: dataProp.key?.name,
                                        value: dataProp.value,
                                    });
                                } else {
                                    // Push ref conversion to fixes array
                                    fixes.push({
                                        type: 'dataRef',
                                        key: dataProp.key?.name,
                                        value: dataProp.value.raw,
                                    })
                                }
                            });

                            // Check if after computedNode there is a trailing comma
                            const commaToken = context.getTokenAfter(dataNode);
                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }

                            // Remove the data property from the component
                            yield fixer.remove(dataNode);
                        }

                        // Handle `computed: {...}` conversion to `const computed = ...`
                        if (computedNode) {
                            const computedProperties = computedNode.value.properties;

                            // Loop through each computed property and convert to computed
                            computedProperties.forEach(computedProp => {
                                // Check if computed value is writable (ObjectExpression)
                                if (computedProp.value?.type === 'ObjectExpression') {
                                    // Convert old writable computed property to new computed property
                                    fixes.push({
                                        type: 'writableComputed',
                                        key: computedProp.key?.name,
                                        value: computedProp.value,
                                    });
                                    return;
                                }

                                // Check if computedProp is a spread property
                                if (computedProp.type === 'SpreadElement') {
                                    // Get the computed property name from the spread property
                                    const computedPropName = computedProp.argument.callee.name;

                                    // Get the value
                                    const computedPropArgument = computedProp.argument;

                                    // Convert old normal computed property to new computed property
                                    fixes.push({
                                        type: 'spreadComputed',
                                        key: computedPropName,
                                        value: computedPropArgument,
                                    });
                                    return;
                                }

                                // Skip if computedProp.value is not defined
                                if (!computedProp.value) return;

                                // Convert old normal computed property to new computed property
                                fixes.push({
                                    type: 'computed',
                                    key: computedProp.key?.name,
                                    value: computedProp.value,
                                });
                            });

                            // Check if after computedNode there is a trailing comma
                            const commaToken = context.getTokenAfter(computedNode);
                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }

                            // Remove the computed property from the component
                            yield fixer.remove(computedNode);
                        }

                        // Handle `methods: {...}` conversion to `const method = ...`
                        if (methodsNode) {
                            const methodsProperties = methodsNode.value.properties;

                            // Loop through each method property and convert to function
                            methodsProperties.forEach(methodProp => {
                                // Convert old method property to new function
                                fixes.push({
                                    type: 'method',
                                    key: methodProp.key?.name,
                                    value: methodProp.value,
                                });
                            });

                            // Check if after methodsNode there is a trailing comma
                            const commaToken = context.getTokenAfter(methodsNode);
                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }

                            // Remove the methods property from the component
                            yield fixer.remove(methodsNode);
                        }

                        // Handle `watch: {...}` conversion to `watch()`
                        if (watchNode) {
                            const watchProperties = watchNode.value.properties;

                            // Loop through each watch property and convert to watch
                            watchProperties.forEach(watchProp => {
                                // Convert old watch property to new watch
                                fixes.push({
                                    type: 'watch',
                                    key: watchProp.key?.name,
                                    value: watchProp.value,
                                });
                            });

                            // Check if after watchNode there is a trailing comma
                            const commaToken = context.getTokenAfter(watchNode);
                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }

                            // Remove the watch property from the component
                            yield fixer.remove(watchNode);
                        }

                        // Handle `mounted() {...}` conversion to `onMounted() {...}`
                        if (mountedNode) {
                            // Convert value to onMounted
                            fixes.push({
                                type: 'lifecycle',
                                key: 'onMounted',
                                value: mountedNode.value,
                            });

                            // Remove the mounted property from the component
                            yield fixer.remove(mountedNode);

                            // Check if after mountedNode there is a trailing comma
                            const commaToken = context.getTokenAfter(mountedNode);

                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }
                        }

                        // Handle `updated() {...}` conversion to `onUpdated() {...}`
                        if (updatedNode) {
                            // Convert value to onUpdated
                            fixes.push({
                                type: 'lifecycle',
                                key: 'onUpdated',
                                value: updatedNode.value,
                            });

                            // Remove the updated property from the component
                            yield fixer.remove(updatedNode);

                            // Check if after updatedNode there is a trailing comma
                            const commaToken = context.getTokenAfter(updatedNode);

                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }
                        }

                        // Handle `unmounted() {...}` conversion to `onUnmounted() {...}`
                        if (unmountedNode) {
                            // Convert value to onUnmounted
                            fixes.push({
                                type: 'lifecycle',
                                key: 'onUnmounted',
                                value: unmountedNode.value,
                            });

                            // Remove the unmounted property from the component
                            yield fixer.remove(unmountedNode);

                            // Check if after unmountedNode there is a trailing comma
                            const commaToken = context.getTokenAfter(unmountedNode);

                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }
                        }

                        // Handle `beforeMount() {...}` conversion to `onBeforeMount() {...}`
                        if (beforeMountNode) {
                            // Convert value to onBeforeMount
                            fixes.push({
                                type: 'lifecycle',
                                key: 'onBeforeMount',
                                value: beforeMountNode.value,
                            });

                            // Remove the beforeMount property from the component
                            yield fixer.remove(beforeMountNode);

                            // Check if after beforeMountNode there is a trailing comma
                            const commaToken = context.getTokenAfter(beforeMountNode);

                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }
                        }

                        // Handle `beforeUpdate() {...}` conversion to `onBeforeUpdate() {...}`
                        if (beforeUpdateNode) {
                            // Convert value to onBeforeUpdate
                            fixes.push({
                                type: 'lifecycle',
                                key: 'onBeforeUpdate',
                                value: beforeUpdateNode.value,
                            });

                            // Remove the beforeUpdate property from the component
                            yield fixer.remove(beforeUpdateNode);

                            // Check if after beforeUpdateNode there is a trailing comma
                            const commaToken = context.getTokenAfter(beforeUpdateNode);

                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }
                        }

                        // Handle `beforeUnmount() {...}` conversion to `onBeforeUnmount() {...}`
                        if (beforeUnmountNode) {
                            // Convert value to onBeforeUnmount
                            fixes.push({
                                type: 'lifecycle',
                                key: 'onBeforeUnmount',
                                value: beforeUnmountNode.value,
                            });

                            // Remove the beforeUnmount property from the component
                            yield fixer.remove(beforeUnmountNode);

                            // Check if after beforeUnmountNode there is a trailing comma
                            const commaToken = context.getTokenAfter(beforeUnmountNode);
                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }
                        }

                        // Handle `created() {...}` conversion to `onBeforeMount() {...}`
                        if (createdNode) {
                            // Convert value to onBeforeMount
                            fixes.push({
                                type: 'lifecycle',
                                key: 'onBeforeMount',
                                value: createdNode.value,
                            });

                            // Remove the created property from the component
                            yield fixer.remove(createdNode);

                            // Check if after createdNode there is a trailing comma
                            const commaToken = context.getTokenAfter(createdNode);
                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }
                        }

                        // Handle `beforeCreate() {...}` conversion to `onBeforeMount() {...}`
                        if (beforeCreateNode) {
                            // Convert value to onBeforeMount
                            fixes.push({
                                type: 'lifecycle',
                                key: 'onBeforeMount',
                                value: beforeCreateNode.value,
                            });

                            // Remove the beforeCreate property from the component
                            yield fixer.remove(beforeCreateNode);

                            // Check if after beforeCreateNode there is a trailing comma
                            const commaToken = context.getTokenAfter(beforeCreateNode);
                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }
                        }

                        // Handle `beforeDestroy() {...}` conversion to `onBeforeUnmount() {...}`
                        if (beforeDestroyNode) {
                            // Convert value to onBeforeUnmount
                            fixes.push({
                                type: 'lifecycle',
                                key: 'onBeforeUnmount',
                                value: beforeDestroyNode.value,
                            });

                            // Remove the beforeDestroy property from the component
                            yield fixer.remove(beforeDestroyNode);

                            // Check if after beforeDestroyNode there is a trailing comma
                            const commaToken = context.getTokenAfter(beforeDestroyNode);
                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }
                        }

                        // Handle `destroyed() {...}` conversion to `onUnmounted() {...}`
                        if (destroyedNode) {
                            // Convert value to onUnmounted
                            fixes.push({
                                type: 'lifecycle',
                                key: 'onUnmounted',
                                value: destroyedNode.value,
                            });

                            // Remove the destroyed property from the component
                            yield fixer.remove(destroyedNode);

                            // Check if after destroyedNode there is a trailing comma
                            const commaToken = context.getTokenAfter(destroyedNode);
                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }
                        }

                        // Handle `inject: {...}` conversion to `inject()`
                        if (injectNode) {
                            // Convert value to inject
                            fixes.push({
                                type: 'inject',
                                key: 'inject',
                                value: injectNode.value,
                            });

                            // Remove the inject property from the component
                            yield fixer.remove(injectNode);

                            // Check if after injectNode there is a trailing comma
                            const commaToken = context.getTokenAfter(injectNode);
                            if (commaToken.value === ',') {
                                yield fixer.remove(commaToken);
                            }
                        }

                        let innerSetupCode = '';
                        let returnStateValues = [];

                        /**
                         * Sort the fixes array by the fix.type property to
                         * reduce most of the "... was used before it was defined" errors
                         * when replacing the `this` references with direct references.
                         *
                         * The order is:
                         * 1. inject
                         * 2. ref
                         * 3. reactive
                         * 4. computed
                         * 5. writableComputed
                         * 6. method
                         * 7. lifecycle
                         * 8. watch
                         */
                        fixes = fixes.sort((a, b) => {
                            const order = ['inject', 'ref', 'reactive', 'computed', 'writableComputed', 'method', 'lifecycle', 'watch'];
                            return order.indexOf(a.type) - order.indexOf(b.type);
                        });

                        /**
                         * Loop through each fix and convert the old Vue 2 Options API properties to the new Vue 3 Composition API properties
                         */
                        fixes.forEach(fix => {
                            // Handle inject conversion
                            if (fix.type === 'inject') {
                                // Check if the inject value is a array
                                if (fix.value.type === 'ArrayExpression') {
                                    // Get each inject value of the array
                                    fix.value.elements.forEach(injectValue => {
                                        // Convert value to inject
                                        innerSetupCode += `      const ${injectValue.value} = inject('${injectValue.value}');\n`;

                                        returnStateValues.push({
                                            type: 'inject',
                                            value: `${injectValue.value}`
                                        });
                                    });
                                }

                                // Check if the inject value is an object
                                if (fix.value.type === 'ObjectExpression') {
                                    // Get each inject value of the object
                                    fix.value.properties.forEach(injectValue => {
                                        // Convert value to inject
                                        innerSetupCode += `      const ${injectValue.key.name} = inject('${injectValue.key.name}');\n`;

                                        returnStateValues.push({
                                            type: 'inject',
                                            value: `${injectValue.key.name}`
                                        });
                                    });
                                }

                                // Add inject to needed imports
                                if (!neededImports.includes('inject')) {
                                    neededImports.push('inject');
                                }
                            }

                            // Handle data ref conversion
                            if (fix.type === 'dataRef') {
                                // Convert value to ref
                                innerSetupCode += `      const ${fix.key} = ref(${fix.value});\n`;

                                // Add ref to needed imports
                                if (!neededImports.includes('ref')) {
                                    neededImports.push('ref');
                                }

                                returnStateValues.push({
                                    type: 'ref',
                                    value: `${fix.key}`
                                });
                            }

                            // Handle data reactive conversion
                            if (fix.type === 'dataReactive') {
                                // fix.value is ObjectExpression
                                // Get raw value of the object
                                const objectValue = context.getSourceCode().getText(fix.value);
                                // Convert value to reactive
                                innerSetupCode += `      const ${fix.key} = reactive(${objectValue});\n`;

                                // Add reactive to needed imports
                                if (!neededImports.includes('reactive')) {
                                    neededImports.push('reactive');
                                }

                                returnStateValues.push({
                                    type: 'reactive',
                                    value: `${fix.key}`
                                });
                            }

                            // Handle normal computed conversion
                            if (fix.type === 'computed') {
                                // // Get raw value of the computed function body
                                let computedValue = context.getSourceCode().getText(fix.value?.body);

                                // Convert value to computed
                                innerSetupCode += `          const ${fix.key} = computed(() => ${computedValue});\n`;

                                // Add computed to needed imports
                                if (!neededImports.includes('computed')) {
                                    neededImports.push('computed');
                                }

                                returnStateValues.push({
                                    type: 'computed',
                                    value: `${fix.key}`
                                });
                            }

                            // Handle spread computed conversion
                            if (fix.type === 'spreadComputed') {
                                // Get raw value of the computed function body
                                let computedValue = context.getSourceCode().getText(fix.value);

                                // Write a comment to inform the developer that the spread computed property is not fully supported yet
                                // The comment also contains the original code
                                innerSetupCode += "\n" +
                                    "    /** TODO: Spread computed property is not fully supported yet. Original code:" +
                                    "\n" +
                                    "        " + computedValue +
                                    "\n" +
                                    "    */\n";
                            }

                            // Handle writable computed conversion
                            if (fix.type === 'writableComputed') {
                                // Get raw value of the object, e.g. { get: ..., set: ... }
                                const objectValue = context.getSourceCode().getText(fix.value);

                                // Convert old writable computed property to new computed property for composition API
                                innerSetupCode += `          const ${fix.key} = computed(${objectValue});\n`;

                                // Add computed to needed imports
                                if (!neededImports.includes('computed')) {
                                    neededImports.push('computed');
                                }

                                returnStateValues.push({
                                    type: 'writableComputed',
                                    value: `${fix.key}`
                                });
                            }

                            // Handle method conversion
                            if (fix.type === 'method') {
                                // Get raw value of the method function body
                                let methodValue = context.getSourceCode().getText(fix.value);
                                // Check if method is async
                                const isAsync = fix.value.async ?? false;
                                // Check if method has TS return type
                                const hasReturnType = fix.value.returnType ?? false;

                                // Add async keyword if the method is async
                                if (isAsync) {
                                    methodValue = `async ${methodValue}`;
                                }

                                // Check if method is FunctionExpression, then convert to arrow function
                                if (fix.value.type === 'FunctionExpression' && !hasReturnType) {
                                    /**
                                     * Handle non-arrow functions, e.g. `function() { ... }`
                                     */
                                    if (methodValue.startsWith('function() {')) {
                                        methodValue = methodValue.replace('function() {', '() => {');
                                    }

                                    /**
                                     * Handle normal method conversion when arguments are present, e.g. `(arg1, arg2) { ... }`
                                     * to arrow function, e.g. `(arg1, arg2) => { ... }`
                                     */
                                    if (methodValue.includes(') {')) {
                                        methodValue = methodValue.replace(') {', ') => {');
                                    }
                                }

                                // Check if method is FunctionExpression and has a return type
                                if (fix.value.type === 'FunctionExpression' && hasReturnType) {
                                    // Get the return type of the method
                                    const returnType = context.getSourceCode().getText(fix.value.returnType);
                                    // Remove the return type from the method
                                    methodValue = methodValue.replace(returnType, '');
                                    // Convert normal method to arrow function
                                    if (methodValue.includes(') {')) {
                                        methodValue = methodValue.replace(') {', ') => {');
                                    }
                                    // Find index of the first closing argument bracket
                                    const closingBracketIndex = methodValue.indexOf(') =>') + 1;
                                    // Insert the return type after the closing bracket
                                    methodValue = methodValue.slice(0, closingBracketIndex) + returnType + methodValue.slice(closingBracketIndex);
                                }

                                // Convert value to function
                                innerSetupCode += `      const ${fix.key} = ${methodValue};\n`;

                                returnStateValues.push({
                                    type: 'method',
                                    value: `${fix.key}`
                                });
                            }

                            // Handle lifecycle conversion
                            if (fix.type === 'lifecycle') {
                                // Get raw value of the lifecycle function body
                                let lifecycleValue = context.getSourceCode().getText(fix.value);

                                // Convert normal method to arrow function
                                if (lifecycleValue.includes(') {')) {
                                    lifecycleValue = lifecycleValue.replace(') {', ') => {');
                                }

                                // Add lifecycle to needed imports
                                if (!neededImports.includes(fix.key)) {
                                    neededImports.push(fix.key);
                                }

                                // Convert value to lifecycle
                                innerSetupCode += `      ${fix.key}(${lifecycleValue});\n`;
                            }

                            // Handle watch conversion
                            if (fix.type === 'watch') {
                                // If the watch value is a object expression
                                if (fix.value.type === 'ObjectExpression') {
                                    const watchHandler = fix.value.properties.find(prop => prop.key.name === 'handler');
                                    const watchImmediate = fix.value.properties.find(prop => prop.key.name === 'immediate');
                                    const watchDeep = fix.value.properties.find(prop => prop.key.name === 'deep');

                                    // Get raw value of the watch function body
                                    let watchValue = context.getSourceCode().getText(watchHandler.value);

                                    // Convert normal method to arrow function
                                    if (watchValue.includes(') {')) {
                                        watchValue = watchValue.replace(') {', ') => {');
                                    }

                                    // Convert value to watch
                                    innerSetupCode += `      watch(${fix.key}, ${watchValue}`;

                                    // Add open bracket for options
                                    if (watchImmediate || watchDeep) {
                                        innerSetupCode += ', {';
                                    }

                                    // Add watch immediate option
                                    if (watchImmediate) {
                                        innerSetupCode += ` immediate: ${watchImmediate.value.value}`;
                                    }

                                    // Add watch deep option
                                    if (watchDeep) {
                                        if (watchImmediate) {
                                            innerSetupCode += `,`;
                                        }

                                        innerSetupCode += ` deep: ${watchDeep.value.value}`;
                                    }

                                    // Add close bracket for options
                                    if (watchImmediate || watchDeep) {
                                        innerSetupCode += ` }`;
                                    }

                                    innerSetupCode += `);\n`;

                                    // Add watch to needed imports
                                    if (!neededImports.includes('watch')) {
                                        neededImports.push('watch');
                                    }
                                }

                                // If the watch value is a function expression
                                if (fix.value.type === 'FunctionExpression') {
                                    // Get raw value of the watch function body
                                    let watchValue = context.getSourceCode().getText(fix.value);

                                    // Convert normal method to arrow function
                                    if (watchValue.includes(') {')) {
                                        watchValue = watchValue.replace(') {', ') => {');
                                    }

                                    // Convert value to watch
                                    innerSetupCode += `      watch(${fix.key}, ${watchValue});\n`;

                                    // Add watch to needed imports
                                    if (!neededImports.includes('watch')) {
                                        neededImports.push('watch');
                                    }
                                }
                            }

                        });

                        /**
                         * Handle all reactive value reassigments for reactive values, e.g.:
                         *
                         * const tax = reactive({});
                         *
                         * // later in Code:
                         * ....then((updatedTax) => {
                         *     tax = updatedTax
                         *     // should be replaced with
                         *     Object.assign(tax, updatedTax);
                         * });
                         */
                        const reactiveValues = returnStateValues.filter(({ type }) => type === 'reactive');
                        reactiveValues.forEach(({ value }) => {
                            // Check if the reactive object is reassigned
                            if (innerSetupCode.includes(`${value} =`)) {
                                // Find all reassignments of the reactive object
                                let reassignments = innerSetupCode.match(new RegExp(`${value} = .*`, 'g'));

                                // Delete every const or let assignment in the reassignments array
                                reassignments = reassignments.filter(reassignment => {
                                    // Get the word before the reassignment from the innerSetupCode
                                    const splittedInnerSetupIndex = innerSetupCode.indexOf(reassignment);
                                    // Get the 6 previous characters from the reassignment
                                    const wordBeforeReassignment = innerSetupCode.slice(splittedInnerSetupIndex - 6, splittedInnerSetupIndex);
                                    // Check if the word before the reassignment contains a const or let keyword
                                    return !wordBeforeReassignment.includes('const') && !wordBeforeReassignment.includes('let');
                                });

                                // Delete every == or === assignment in the reassignments array
                                reassignments = reassignments.filter(reassignment => {
                                    return !reassignment.includes('==');
                                });

                                // Replace all reassignments of the reactive object with Object.assign
                                reassignments.forEach(reassignment => {
                                    let reassignmentValue = reassignment.split('=')[1].trim();

                                    // Check if a semicolon is at the end of the reassignment
                                    if (reassignmentValue.endsWith(';')) {
                                        reassignmentValue = reassignmentValue.slice(0, -1);
                                        // Only replace the reassignment if it is not a multiline assignment, so it has a semicolon at the end
                                        innerSetupCode = innerSetupCode.replace(reassignment, `Object.assign(${value}, ${reassignmentValue});`);
                                    }
                                    // Handle multiline reassignments
                                    else {
                                        // Get the original node of the assignment expression out of the context
                                        const sourceCode = context.getSourceCode().getText();
                                        const reassignmentIndex = sourceCode.indexOf(reassignment);
                                        const originalReassignmentNode = context.getSourceCode().getNodeByRangeIndex(reassignmentIndex);
                                        let expressionStatementParent;

                                        // Find the parent of the assignment expression
                                        if (originalReassignmentNode?.parent?.type === 'ExpressionStatement') {
                                            expressionStatementParent = originalReassignmentNode.parent;
                                        } else if (originalReassignmentNode?.parent?.type === 'MemberExpression') {
                                            expressionStatementParent = originalReassignmentNode.parent.parent;
                                        }

                                        // Only handle multiline reassignments if the parent is an expression statement
                                        if (expressionStatementParent) {
                                            // Get the right side of the expression statement parent
                                            const rightSideExpression = context.getSourceCode().getText(expressionStatementParent.right);

                                            // Get only the right side expression without the first line
                                            const rightSideExpressionWithoutFirstLine = rightSideExpression.split('\n').slice(1).join('\n');
                                            // Remove the old multi-line reassignment from the innerSetupCode
                                            innerSetupCode = innerSetupCode.replace(rightSideExpressionWithoutFirstLine, '');

                                            // Replace the reassignment with Object.assign and the new right side expression
                                            innerSetupCode = innerSetupCode.replace(reassignment, `Object.assign(${value}, ${rightSideExpression})`);
                                        }
                                    }
                                });
                            }
                        });

                        // Replace all `this.Object.assign` references inside the `innerSetupCode` code with `Object.assign`
                        innerSetupCode = innerSetupCode.replace(new RegExp(`this.Object.assign`, 'g'), 'Object.assign');

                        /**
                         * Replace all `this` references inside the `innerSetupCode` code
                         * with direct references when they are inside the returnStateValues
                         */
                        returnStateValues.forEach(({ type, value }) => {
                            const searchExpression = new RegExp(`this\\.${value}(?!\\w)`, 'g');

                            // Ref replacement
                            if (type === 'ref') {
                                innerSetupCode = innerSetupCode.replace(searchExpression, `${value}.value`);
                                return;
                            }

                            // Computed replacement
                            if (type === 'computed' || type === 'writableComputed') {
                                innerSetupCode = innerSetupCode.replace(searchExpression, `${value}.value`);
                                return;
                            }

                            // Normal replacement
                            innerSetupCode = innerSetupCode.replace(searchExpression, value);
                        });

                        let propsUsedInsideSetup = false;

                        /**
                         * Replace all `this.` references for props to `props.` when
                         * they are inside the `innerSetupCode` code
                         */
                        if (propsNode) {
                            // If props are defined in array format
                            if (propsNode.value.type === 'ArrayExpression') {
                                // Get each props property of the array
                                propsNode.value.elements.forEach(propsProp => {
                                    // Check if the props are used inside the setup function
                                    if (innerSetupCode.includes(`this.${propsProp.value}`)) {
                                        propsUsedInsideSetup = true;
                                    }

                                    // Replace all `this.` references for props to `props.`
                                    innerSetupCode = innerSetupCode.replace(new RegExp(`this.${propsProp.value}`, 'g'), `props.${propsProp.value}`);

                                    // Check if the props are accessed in watch() function
                                    if (innerSetupCode.includes(`watch(${propsProp.value}`)) {
                                        propsUsedInsideSetup = true;

                                        // Replace all watch() references for props to props.
                                        innerSetupCode = innerSetupCode.replace(new RegExp(`watch\\(${propsProp.value}`, 'g'), `watch(props.${propsProp.value}`);
                                    }
                                });
                            }

                            // If props are defined in object format
                            if (propsNode.value.type === 'ObjectExpression') {
                                // Get each props property of the object
                                propsNode.value.properties.forEach(propsProp => {
                                    // Check if the props are used inside the setup function
                                    if (innerSetupCode.includes(`this.${propsProp.key.name}`)) {
                                        propsUsedInsideSetup = true;
                                    }

                                    // Replace all `this.` references for props to `props.`
                                    innerSetupCode = innerSetupCode.replace(new RegExp(`this.${propsProp.key.name}`, 'g'), `props.${propsProp.key.name}`);

                                    // Check if the props are accessed in watch() function
                                    if (innerSetupCode.includes(`watch(${propsProp.key.name}`)) {
                                        propsUsedInsideSetup = true;

                                        // Replace all watch() references for props to props.
                                        innerSetupCode = innerSetupCode.replace(new RegExp(`watch\\(${propsProp.key.name}`, 'g'), `watch(props.${propsProp.key.name}`);
                                    }
                                });
                            }
                        }

                        // Create setup function as a property inside the componentObjectExpression as the first property
                        const firstProperty = componentProperties[0];

                        // Check if the first property exists
                        if (!firstProperty) return;

                        // Insert the setup function before the first property
                        yield fixer.insertTextBefore(firstProperty,
                            '' +
                            // Add the setup function
                            'setup(' + (propsUsedInsideSetup ? 'props' : '') + ') {\n' +
                            // Add inner setup code, e.g. the refs, reactive, computed, etc.
                            '    ' + `${innerSetupCode}` +
                            '\n' +
                            // Return result of setup function
                            '          ' + `return {` +
                            `${returnStateValues.reduce((acc, { type: stateType, value: stateName}) => {
                                // Add each property to the return object
                                acc = acc + `\n            ${stateName},`;
                                return acc;
                            }, '')}` +
                            `\n          };\n` +
                            '        },\n'
                        );
                    }
                });
            },
            'Program:exit'() {
                // Add all needed imports at the end of the file
                if (neededImports.length) {
                    /**
                     * Create import statements for all needed imports like this:
                     * import { ref, reactive, computed, watch, onMounted, onUpdated, onUnmounted, onBeforeMount, onBeforeUpdate, onBeforeUnmount } from 'vue';
                     *
                     * Add the import statements at the beginning of the file
                     */
                    const importStatement = `import { ${neededImports.join(', ')} } from 'vue';\n`;

                    // Get the first node of the program
                    const firstNode = context.getSourceCode().ast.body[0];

                    // Insert the import statement before the first node
                    context.report({
                        node: firstNode,
                        message: 'Add all missing Vue composition API imports at the beginning of the file',
                        *fix(fixer) {
                            yield fixer.insertTextBefore(firstNode, importStatement);
                        }
                    });
                }
            }
        };
    }
};
