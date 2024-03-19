
Maybe someting like this? Instead of providing big data objects, allow database/service access inside the rule. Use yield key-value pairs to collect data just once and then use it in the match method. 

Instead of injecting only the connection, we could inject a Service locator, so we can access multiple services and allow dependency injection and keep control of the rule constructor. 

Input would be an equivalent of the current flow data collection. 

```php
class IsNewCustomerRule extends FlowRule
{
    public function collect(Input $input, Connection $connection): \Generator
    {
        yield 'is-new-customer' => function() use ($connection, $input) {
            return $connection->fetchOne(
                'SELECT COUNT(id) FROM `order` WHERE customer_id = :customerId',
                ['customerId' => $input->get('customerId')]
            );
        };
    }

    public function match(DataBag $data): bool
    {
        return $data->get('is-new-customer') === true;
    }

    public function getConstraints(): array
    {
        return [];
    }
}
```
