# Internal

All classes and elements (methods, properties, constants) that are defined as protected or public are initially Public API for third party developers.

The Shopware Public API must be kept compatible with each release. This means that the following must not change for third party developers in a minor release:
- The developer uses a service to use certain functions.
- The developer decorates a service to extend its functionality.
- The developer uses DTO to get or pass data.

There are various other use-cases for third party developers, but the above reflect the standards.

However, if all classes and properties had to be considered public api by us, we would be very limited in our work.

Therefore, we mark the elements that we do not consider to be public API. To do this, we have the following tools at our disposal.

## Decoration pattern
Classes that are intended for **service decoration** are provided with an abstract class. This class is then provided with a `getDecorated` function to pass unimplemented functions directly to the core classes. [Read more](https://github.com/shopware/platform/blob/trunk/adr/2020-11-25-decoration-pattern.md)

## Final classes
Tendentiously, just about all classes in Shopware should be declared as `final`. We do this for the following reasons:
- We declare a DI container service as `final` so that it will not be extended. All services that can be exchanged via DI-Container have an `abstract class` implementation. Per `extends` from core services is not intended.
- We declare **DTO classes** as `final` to indicate that we do not intend third party developers to derive from these classes. To append more data to DTO's we use the base `Struct` class which allows **Extensions**.
- We declare **Event Subscriber** as `final` as we do not foresee deriving from them in order to leverage the events or extend their functionality.

Classes that we declare as `final` are still Public API, because **Third Party Developers are consumers** of these classes. That means they access the public methods and functions of the classes.

## Internal annotation
Classes where we want to reserve a complete **refactoring** or where we only implemented them to not implement "a big master class" in a domain, we mark them with the doc block `@internal`.

Classes with this annotation may change completely with each release and are therefore not intended for use by third party developers.

## Internal interfaces
We declare interfaces as `@internal` when we want to implement multiple implementations of a feature or adapter, but do not want third party developers to interfere in this area of the software. A good example of this is the Data Abstraction layer and the Field and FieldSerializer classes. In such areas of the domain we want to reserve optimizations and breaks within minor versions but still be able to work with interfaces and abstract classes. 
