/**
 * @sw-package framework
 * @private
 */
async function selectMtSelectOptionByText(wrapper, text, selector = '.mt-select input') {
    // Step 1 open result list
    const mtSelectInput = wrapper.find(selector);
    expect(mtSelectInput.isVisible()).toBe(true);
    await mtSelectInput.trigger('click');
    await flushPromises();

    // Step 2 result list should have opened
    const popover = wrapper.findComponent('.mt-popover-deprecated');
    expect(popover.isVisible()).toBe(true);

    // Step 3 check the option exists
    const option = popover.findByText('li', text);
    expect(option.isVisible()).toBe(true);

    // Step 4 select option
    await option.trigger('click');
    await flushPromises();
}

export default selectMtSelectOptionByText;
