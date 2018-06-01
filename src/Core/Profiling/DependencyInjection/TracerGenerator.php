<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DependencyInjection;

class TracerGenerator
{
    private $directory;

    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    public function createTracer($class, string $label, string $section = 'shopware'): string
    {
        $reflection = new \ReflectionClass($class);

        $functionTemplate = '
public function #name#(#parameters#) #returnType# {
    $e = $this->stopwatch->start(\'#eventname#\', \'#section#\');

    $result = $this->decorated->#name#(#callparams#);

    if ($e->isStarted()) $e->stop();
    #returnresult#
}
        ';

        $functions = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->name === '__construct') {
                continue;
            }

            $parameters = [];
            $calls = [];

            foreach ($method->getParameters() as $param) {
                $type = '';
                if ($param->getClass()) {
                    $type = '\\' . $param->getClass()->getName();
                } elseif ($param->getType()) {
                    $type = $param->getType()->getName();
                }

                $parameters[] = $type . ' $' . $param->getName();
                $calls[] = '$' . $param->getName();
            }

            $parameters = implode(',', $parameters);
            $calls = implode(',', $calls);

            $return = '';
            if ($method->getReturnType()) {
                $return = $method->getReturnType()->getName();
                if (strpos($return, '\\') > 0) {
                    $return = '\\' . $return;
                }
                $return = ':' . $return;
            }

            $functionReturn = '';
            if ($return !== ':void') {
                $functionReturn = 'return $result;';
            }

            $functionLabel = $label . '.' . $method->name;

            $functions[] = str_replace(
                ['#name#', '#parameters#', '#returnType#', '#eventname#', '#callparams#', '#returnresult#', '#section#'],
                [$method->name, $parameters, $return, $functionLabel, $calls, $functionReturn, $section],
                $functionTemplate
            );
        }

        $originalClass = $reflection->name;
        $lastPart = explode('\\', $originalClass);
        $className = array_pop($lastPart) . 'Tracer';

        $functions = implode("\n", $functions);

        $template = str_replace(
            ['#className#', '#originalClass#', '#functions#'],
            [$className, '\\' . $originalClass, $functions],
            '<?php

namespace ShopwareTracer;

if (!class_exists(#originalClass#::class)) {
    return;
}

class #className# extends #originalClass#
{

    /**
     * @var #originalClass#
     */
    private $decorated;
    
    /**
     * @var \Symfony\Component\Stopwatch\Stopwatch
     */
    private $stopwatch;

    public function __construct($decorated, \Symfony\Component\Stopwatch\Stopwatch $stopwatch)
    {
        $this->decorated = $decorated;
        $this->stopwatch = $stopwatch;
    }
    
    #functions#
}'
        );

        $file = $this->directory . '/' . $className . '.php';
        file_put_contents($file, $template);

        $className = 'ShopwareTracer\\' . $className;

        if (!class_exists($className)) {
            require_once $file;
        }

        return $className;
    }
}
