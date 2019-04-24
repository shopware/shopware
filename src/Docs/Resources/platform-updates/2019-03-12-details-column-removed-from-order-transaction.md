[titleEn]: <>(Details column removed from OrderTransaction)
[__RAW__]: <>(__RAW__)

<p>We removed the details column from the order_transaction table. It was introduced in the past, to store additional data to the transaction, e.g. from external payment providers. This is now unnecessary since the introduction of the custom field. If you stored data to the details field, create a new custom field and store the data in this custom field field. An example migration could be found in our <a href="https://github.com/shopwareLabs/SwagPayPal/commit/a09beec33c5ebe8247d259e970dfcc09ee9c8f13">PayPal integration</a></p>
