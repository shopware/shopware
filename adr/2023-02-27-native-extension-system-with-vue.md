---
title: Native extension system with vue
date: 2023-02-27
area: administration
tags: [vue, extensibility, performance, administration]
---

## Context
Our current plugin extension system for the administration is based on our Component Factory. This factory generates native Vue components at runtime based on a base Shopware component which can be extended or overwritten by plugins. This approach offers flexibility to plugin developers to modify every component. For the template part, we use Twig.JS and compile the Vue template in the client at runtime.

However, this approach has its drawbacks. We cannot use the full power of Vue and its ecosystem because everything related to Vue is wrapped with our factories and generated at runtime. We cannot use Vue tools without custom modifications, like linting template files, providing good static analysis, and more. The other downside is that upgrading Vue is challenging, and the performance suffers because we cannot precompile components.

To address these issues, we aimed to test a new idea with Vue 3, which includes all the features of the existing plugin approach, but developed using only native Vue tools. This would offer several benefits, like using the full power of Vue, better performance, better static analysis, and more.

Our main idea for the template part was to use native Vue components named sw-block that can replace the Twig.JS template part. Plugin developers can extend or overwrite the sw-block component as needed.

```html
<sw-block name="sw-hello-world">
<div>Hello World</div>
</sw-block>
```

For the script and logic part, we aimed to use the Composition API. Before returning all the component data and methods, we would provide a hook point for plugins so they could modify or inject everything they want.

```js
// The original component
<script setup lang="ts">
// Hook for providing extensibility
useExtensibility();
</script>

// The plugin component
<script lang="ts">
    import SwOrignal from './sw-original.vue';
    // use our extensibility helpers
    import { useComponentExtender, getPropsFromComponent } from 'sw-vue-extensbiles';
    
    const myCustomData = ref('test');
    
    export default {
        name: 'sw-extended-component',
        props: {
            ...getPropsFromComponent(SwOrignal)
        },
        setup(props, context) {
            return {
                ...useComponentExtender(SwOrignal, props, context),
                myCustomData
            }   
        }
}
</script>
```

However, during the evaluation and testing, we realized that this approach was not feasible due to several reasons. The most critical challenges were that it was hard to merge data because the Vue compiler optimizes many parts of the component, and it was not easy to pass all the data of the component to the block system to give plugin developers full access to the original component's data.

To solve these problems, we would need to use internal Vue logic, which is not update-safe and could break with every Vue update.

## Decision
After considering the challenges we faced with the proposed solution, we concluded that we couldn't find solutions without using internal Vue logic. Given this situation, we did not see any significant benefits to adopting a native Vue solution over our current plugin system, which, despite its challenges, has proven to work well for our use case.

Therefore, we decided to stick with our current plugin system for the administration until new possibilities arise in Vue that solve our problems.

## Consequences
We will continue to use the current plugin system for the administration and not switch to a native Vue solution.


