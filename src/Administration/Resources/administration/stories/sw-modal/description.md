This component provides a modal template.

In general the component can be called inside the template like every other component.

You will have to tell the modal component with a `v-if` condition if it should be rendered or not.<br>
This is important in order to make the internal `mounted()` lifecycle hook work as expected.<br>
The component itself does not hold any state information if it is currently displayed.<br>
This should be handled from the outside module so every modal component can be independent.

When clicking on the close button or the backdrop, the modal component emits an Event `modalClose`.<br>
This can be used to close every modal independently.