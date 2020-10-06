# 2020-10-06 - Optimize opcode usage

## Context
PHP resolves relative references all the time resulting in native functions, when being called, using more operations when not being invoked correctly.  
Since the performance in large frameworks is heavily impacted when additional and unnecessary operations are being run constantly, this should be an important topic to talk about. 

References:  
[PHP opcodes list](https://www.php.net/manual/de/internals2.opcodes.list.php)  
[PHP interpreter source code](https://github.com/php/php-src/blob/f2db305fa4e9bd7d04d567822687ec714aedcdb5/Zend/zend_compile.c#L3872)  
[Benchmarks](https://github.com/Roave/FunctionFQNReplacer#rationale)  
[Detailed article](https://veewee.github.io/blog/optimizing-php-performance-by-fq-function-calls/)

## Decision
Correct native function invocation.

```php
strlen('foo');
```

will be changed to

```php
\strlen('foo');
```

## Consequences
Fewer operations result in a better performance.
