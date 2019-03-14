import Client from "../../service/http-client.service";
import DomAccess from "../../helper/dom-access.helper";
import DeviceDetection from "../../helper/device-detection.helper";
import ButtonLoadingIndicator from "../../plugin/loading-indicator/button-loading-indicator.plugin";

const client = new Client(window.accessKey, window.contextToken);

// TODO: NEXT-2335: refactor and use device detection

let confirmForm = document.getElementById('confirmForm');

if (confirmForm) {
    let confirmFormSubmit = document.getElementById('confirmFormSubmit');

    // register click event on submit button (outside of form) as fallback for unsupported button form attribute
    if (confirmFormSubmit && DeviceDetection.isNativeWindowsBrowser()) {
        confirmFormSubmit.addEventListener('click', function(e) {
            const button = e.srcElement;
            const formId = DomAccess.getAttribute(button, 'form');
            const form = document.getElementById(formId);

            console.log(form);
            // form.submit();
        });
    }

    confirmForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.srcElement;
        const requestUrl = DomAccess.getAttribute(form, 'action');
        const finishUrl = DomAccess.getAttribute(form, 'data-finish-url');

        let loader = new ButtonLoadingIndicator(
            document.querySelector('button[form="' + DomAccess.getAttribute(form, 'id') + '"]')
        );
        loader.create();

        client.post(requestUrl.toLowerCase(), JSON.stringify({}), (response) => {
            let obj = JSON.parse(response);

            if (obj.data.id) {
                window.location = finishUrl + '?orderId=' + obj.data.id;
            }

            loader.remove();
        });
    });
}
