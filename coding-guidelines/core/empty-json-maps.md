# Empty JSON maps

PHP uses one array type for a JSON object and for a JSON array. An empty PHP array therefore encodes as `[]`, whether the field holds a map or a list.

## Rules

- Keep an empty map as an empty PHP array on every write, validation, storage and decode path.
- Never let a `\stdClass` instance or an `(object)` cast reach a value that the DAL validates or stores.
- Return arrays from a value object's `jsonSerialize()` and from a field serializer. The write path uses the output of both too. An empty map in that output is `[]`.
- Keep the object type in the OpenAPI schema of a map field. Document `[]` as the empty shape of that field.

## Why

`JsonFieldSerializer` validates a JSON field with `new Type('array')`. A `\stdClass` instance fails that constraint. `JsonFieldSerializer` then rejects the write.

A JSON round trip hides the defect. `JsonRequestTransformerListener` decodes a JSON request body into arrays with `json_decode()`. A `{}` that a client sends back therefore arrives as `[]`. `JsonFieldSerializer::decode()` does the same for a stored value. The defect appears when code passes a serialized value to a DAL write in memory. No JSON decoding runs on that path. The object therefore stays an object, and `JsonFieldSerializer` rejects it. A test that sends the value over HTTP does not run that path.

## Example

Correct: in the Content System, `StoredElement::jsonSerialize()` emits every empty map as `[]`. The docblock of `StoredElement::jsonSerialize()` states the reason. The Admin API schema `StoredContentElement.json` keeps the object shape for `properties`. The schema adds the empty shape as a second `oneOf` branch:

```json
"oneOf": [
    { "type": "array", "maxItems": 0, "description": "The empty property map, serialized as `[]`." },
    { "type": "object", "additionalProperties": true }
]
```

Incorrect (invented example):

```php
public function jsonSerialize(): array
{
    // The DAL validates this value with Type('array') and rejects the object.
    return ['config' => $this->config === [] ? new \stdClass() : $this->config];
}
```

## What this rule does not cover

- This rule excludes the top-level `customFields` field. `JsonEntityEncoder` and `StructEncoder` replace an empty `customFields` value with a `\stdClass` instance in the response encoder only. `CustomFieldsSerializer` stores the string `'{}'` for an empty value after it has validated an array. Do not copy this swap to another field.
- This rule excludes a response body that no code path writes back or stores. The encoder of such a body may cast an empty map with `(object)`. That permission holds if a test pins the cast and a docblock states that the body is output only. In the Content System, `MutationResponse::jsonSerialize()` casts two map fields this way. `MutationResponseTest` pins the output `"resolutions":{}`.
- This rule excludes the question whether a module omits a field when the field is empty. The module that owns the field defines that behavior.
