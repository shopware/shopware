Storefront Error Handling
-------------------------

On Exceptions a *showHtmlExceptionResponse*-Kernel Event will be fired and catched by the **StorefrontSubscriber**.

The **StorefrontSubscriber** will check if the Request is a Storefrontrequest and then call the **ErrorPageController**.

The **ErrorPageController** determines the right Template for the given error in *Storefront/Resources/views/frontend/error/* and renders it.

A customized Template for specific Exceptions can be implemented by adding an error-Template with the right name depending on the Exception-Code.

The Exceptiontrace will only be rendered in dev mode.

#### Namingconventions for Error-Templates

The Name for an Error-Template must start with *error* followed by prefixes for different Requesttypes and Errorcodes.

The Standardname is **error-std.html.twig**

For Exceptions on Ajaxrequests the prefix **-ajax** will be added. **error-ajax-std.html.twig**

For specific Exceptions a Template with the Errorcode as an prefix can be added. **error-INVALID-UUID.html.twig**

The Errorcode depends on the 
```php 
$exception->getCode()
``` 
String. 
 

