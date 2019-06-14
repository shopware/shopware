[titleEn]: <>(sw-label refactoring)

We refactored `sw-label` component due to a new size and several unused properties.
In addition, we made the usage more simple. Below, you can find the changes in detail:

From now on, you don't need to set the `dismiss` property to make the label dismissable.
The label will automatically provide this possibility as soon as the listener `@dismiss` is registered.

We added the property `size`, providing three sizes:
* `default`: The former default size, with height of `32px`
* `medium`: This size replaces the former `small` property, with height of `24px`
* `small`: A new, small size with `12px` as height.

Furthermore, we merged `pill` into the new property `appearance`. 
The rectangle appearance of the label will stay as default value. As the property `variant` will not automatically
style borders anymore, please use `appearance="pill"` to get the well known look with round edges.

As a result, we deleted following properties:
* `small`
* `dismiss`
* `circle`
* `pill`
* `light`

You can find more detailed information about `sw-label` usage in the 
[Component Library](https://component-library.shopware.com/#/components/sw-label).
