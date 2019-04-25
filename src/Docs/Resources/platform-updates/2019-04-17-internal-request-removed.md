[titleEn]: <>(Internal request removed)

The `InternalRequest` class alternative to the Symfony Request has been removed as it is not required anymore.

To check required parameters etc. use the Symfony Request or even better the `RequestDataBag` or `QueryDataBag` and validate your input using the `DataValidator`. You can see some examples in the `AccountService`.
