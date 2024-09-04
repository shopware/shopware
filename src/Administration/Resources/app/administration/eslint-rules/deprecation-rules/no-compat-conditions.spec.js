const { RuleTester } = require('eslint');
const rule = require('./no-compat-conditions');

const ruleTester = new RuleTester({
    parserOptions: { ecmaVersion: 2021 },
});

ruleTester.run('no-compat-conditions', rule, {
    valid: [
        {
            code: 'group.optionCount = optionCount.length;',
            name: 'allows simple assignment without compat check',
        },
        {
            code: 'if (someOtherCondition) { doSomething(); }',
            name: 'allows if statements with non-compat conditions',
        },
    ],
    invalid: [
        {
          code: `
            if (this.isCompatEnabled('INSTANCE_SET')) {
              this.$set(group, 'optionCount', optionCount.length);
            } else {
              group.optionCount = optionCount.length;
            }
          `,
          output: `
            group.optionCount = optionCount.length;
          `,
          errors: [{ message: 'Feature flag condition should be removed' }],
          name: 'removes compat check and keeps else block for INSTANCE_SET',
        },
        {
            name: 'removes entire if statement when no else block is present',
            code: `
if (this.isCompatEnabled('SOME_OTHER_FLAG')) {
    doSomething();
}
      `,
            output: `

      `,
            errors: [{ message: 'Feature flag condition should be removed' }],
        },
        {
            name: 'handles multi-line statements correctly',
            code: `
        if (this.isCompatEnabled('MULTI_LINE')) {
          doSomething();
          doSomethingElse();
        } else {
          doAnotherThing();
          yetAnotherThing();
        }
      `,
            output: `
        doAnotherThing();
yetAnotherThing();
      `,
            errors: [{ message: 'Feature flag condition should be removed' }],
        },
        {
            name: 'handles nested conditions and preserves non-compat code',
            code: `
        if (someOtherCondition) {
          normalCode();
        }
        if (this.isCompatEnabled('NESTED')) {
          if (anotherCondition) {
            nestedCode();
          } else {
            group.nested = true;
          }
        } else {
          group.nested = false;
        }
      `,
            output: `
        if (someOtherCondition) {
          normalCode();
        }
        group.nested = false;
      `,
            errors: [{ message: 'Feature flag condition should be removed' }],
        },
        {
            name: 'handles Example 1 correctly',
            code: `
        const obj = {
          sliderLength() {
              const children = Shopware.Utils.VueHelper.getCompatChildren();

              if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                  if (this.$children[this.activeItem]) {
                      const activeChildren = this.$children[this.activeItem];
                      return this.isVertical ? activeChildren.$el.offsetHeight : activeChildren.$el.offsetWidth;
                  }
              } else if (children[this.activeItem]) {
                  const activeChildren = children[this.activeItem];
                  return this.isVertical ? activeChildren.$el.offsetHeight : activeChildren.$el.offsetWidth;
              }

              return 0;
          },
        };
      `,
            output: `
        const obj = {
          sliderLength() {
              const children = Shopware.Utils.VueHelper.getCompatChildren();

              if (children[this.activeItem]) {
const activeChildren = children[this.activeItem];
return this.isVertical ? activeChildren.$el.offsetHeight : activeChildren.$el.offsetWidth;
}

              return 0;
          },
        };
      `,
            errors: [{ message: 'Feature flag condition should be removed' }],
        },
        {
            name: 'handles Example 2 correctly',
            code: `
        const obj = {
          toggleSelectedTreeItem(shouldOpen) {
              const vnode = this.findTreeItemVNodeById();

              if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                  if (vnode?.openTreeItem && vnode.opened !== shouldOpen) {
                      vnode.openTreeItem();
                      return true;
                  }
              } else if (
                  !this.isCompatEnabled('INSTANCE_CHILDREN')
                  && vnode?.component?.proxy?.openTreeItem
                  && vnode?.component?.proxy?.opened !== shouldOpen
              ) {
                  vnode.component.proxy.openTreeItem();
                  return true;
              }

              return false;
          },
        };
      `,
            output: `
        const obj = {
          toggleSelectedTreeItem(shouldOpen) {
              const vnode = this.findTreeItemVNodeById();

              if (
!this.isCompatEnabled('INSTANCE_CHILDREN')
&& vnode?.component?.proxy?.openTreeItem
&& vnode?.component?.proxy?.opened !== shouldOpen
) {
vnode.component.proxy.openTreeItem();
return true;
}

              return false;
          },
        };
      `,
            errors: [{ message: 'Feature flag condition should be removed' }],
        },
        {
            name: 'handles compatUtils.isCompatEnabled correctly',
            code: `
        if (compatUtils.isCompatEnabled('GLOBAL_SET')) {
            Vue.set(state.locations, locationId, componentName);
        } else {
            state.locations[locationId] = componentName;
        }
      `,
            output: `
        state.locations[locationId] = componentName;
      `,
            errors: [{ message: 'Feature flag condition should be removed' }],
        },
        {
            name: 'handles multi-line ternary operator correctly',
            code: `
        const children = this.isCompatEnabled('INSTANCE_CHILDREN')
                ? this.$children
                : Shopware.Utils.VueHelper.getCompatChildren();
      `,
            output: `
        const children = Shopware.Utils.VueHelper.getCompatChildren();
      `,
            errors: [{ message: 'Feature flag condition should be removed' }],
        },
        {
            name: 'handles single-line ternary operator correctly',
            code: `const children = this.isCompatEnabled('INSTANCE_CHILDREN') ? this.$children : Shopware.Utils.VueHelper.getCompatChildren();`,
            output: `const children = Shopware.Utils.VueHelper.getCompatChildren();`,
            errors: [{ message: 'Feature flag condition should be removed' }],
        },
        {
            name: 'handles compatUtils.isCompatEnabled in ternary operator',
            code: `const value = compatUtils.isCompatEnabled('SOME_FEATURE') ? newValue : oldValue;`,
            output: `const value = oldValue;`,
            errors: [{ message: 'Feature flag condition should be removed' }],
        },
        {
            name: 'handles negated isCompatEnabled condition correctly',
            code: `
        if (!this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
            this.$root.$off('on-change-notification-center-visibility', this.changeVisibility);
        } else {
            Shopware.Utils.EventBus.$off('on-change-notification-center-visibility', this.changeVisibility);
        }
      `,
            output: `
        this.$root.$off('on-change-notification-center-visibility', this.changeVisibility);
      `,
            errors: [{ message: 'Feature flag condition should be removed' }],
        },
        {
            name: 'handles negated compatUtils.isCompatEnabled condition correctly',
            code: `
        if (!compatUtils.isCompatEnabled('SOME_FEATURE')) {
            oldMethod();
        } else {
            newMethod();
        }
      `,
            output: `
        oldMethod();
      `,
            errors: [{ message: 'Feature flag condition should be removed' }],
        },
    ],
});
