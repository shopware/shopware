[titleEn]: <>(Adding the process button component)

A new component called `sw-button-process` was added to Shopware platform. 
The button is introduced to display the status of the process the button should start. E.g. if you click the button
to save an entity, it will display a loading indicator while the save process is running and a tick icon if the 
process was finished successfully. This way, we tend to get rid of those "Success" notifications which does not
provide any other useful information.

**Usage**

The `sw-button-process` component looks as stated below:
```$html
<sw-button-process
        class="sw-product-detail__save-action"
        :isLoading="isLoading"
        :processSuccess="isSaveSuccessful"
        :disabled="isLoading"
        variant="primary"
        @process-finish="saveFinish"
        @click="onSave">
        {{ $tc('sw-cms.detail.label.buttonSave') }}
</sw-button-process>
```
As you can see, you can use the `sw-button-process` component similar as you're used to with `sw-button`. 
We just need some further information:
* `isLoading`: Necessary to indicate the time when the process is currently running.
* `processSuccess`: This prop signalizes if the process was finished successfully, so that the `sw-button-process`
button can start its success animation.

If you want to use the `sw-button-process` button, you need to change those props accordingly to your module's behavior.

***Events and creation as edge case***

The success animation needs 1.25 seconds to run per default. This way, the `create` pages were a difficulty since
they reload the whole page including the process button, interrupting the animation in the process. For this reason,
we use the `@process-finish` event to signalize that the save process is finished. In a create page, you need to
override this event to navigate to the detail page after the animation ran. As seen in the example below, 
you just need to move the routing to the `saveFinish` event to make it run:

```$javascript
saveFinish() {
    this.isSaveSuccessful = false;
    this.$router.push({ name: 'sw.cms.detail', params: { id: this.page.id } });
},

onSave() {
    this.$super.onSave();
}
``` 
