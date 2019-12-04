[titleEn]: <>(Recovering carts on payment errors)

On payment errors previously there was only a thrown exception. The cart of the customer was lost during the transformation to an order and the checkout needed to be restarted.

Now the order will be transformed back to a cart so the customer can for example retry their order with a different payment method.
