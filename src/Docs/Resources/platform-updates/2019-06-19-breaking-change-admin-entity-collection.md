[titleEn]: <>(Breaking Change: Admin Entity Collection)

We refactored the class ```EntityCollection``` to extend it from the native Array class. This has several advantages.

- The EntityCollection behaves like a normal array and you can use all methods and operations you would use on a normal array.
- You do not longer have to access the items in the collection via ```.items``` because they are just normal items in the array.
- The class still holds the typical collection information like ```context``` or ```critera```.
- You can still use the helper methods of the collection to perform operations based on the ids of the entities.
- We no longer have to use the ```setReactive``` helpers for the reactivity system caveats, which has an enormous performance impact.
- The class comes along with a lot of helper methods, for example for drag and drop sorting

With the refactoring of the EntityCollection we removed its descendant class ```SearchResult``` because the new class combines now both of them. All repository methods always return an ```EntityCollection```. All associated properties in an entity are also an instance of ```EntityCollection```, so now you can access them directly, for example ```product.categories```.
