# Empty JSON maps

PHP uses one array type for a JSON object and for a JSON array. An empty PHP array therefore encodes as `[]`, whether the field holds a map or a list.

## Rules

- Keep an empty map as an empty PHP array on every write, validation, storage and decode path.
- Never let a `\stdClass` instance or an `(object)` cast reach a value that the DAL validates or stores.
- Return arrays from a field serializer and from a value object's `jsonSerialize()` when its output can reach a DAL write. The write path uses that output. An empty map in that output is `[]`.
- Document the empty shape the server sends in the OpenAPI schema. For a map sent as `[]` when empty, use `oneOf` with an object branch and an array branch limited to zero items. For a map sent as `{}`, a request-only map or a map omitted when empty, use an object schema.

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

- This rule excludes `customFields` and `translated.customFields`. `JsonEntityEncoder`, `Record` and `StructEncoder` replace their empty values with a `\stdClass` instance in response encoders only. `CustomFieldsSerializer` stores the string `'{}'` for an empty value after it has validated an array. Do not copy this swap to another field.
- This rule excludes a response body the server only emits and never reads back or stores. Its encoder may cast an empty map with `(object)` if a test pins the cast and a docblock states that the body is output only. In the Content System, `MutationResponse::jsonSerialize()` casts two map fields this way. `MutationResponseTest` pins the output `"resolutions":{}`.
- This rule excludes the question whether a module omits a field when the field is empty. The module that owns the field defines that behavior.
