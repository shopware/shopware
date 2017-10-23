<?php

namespace Shopware\Traceable\DependencyInjection;

class TracerGenerator
{
    private $directory;

    public function __construct($directory = __DIR__ . '/Tracer')
    {
        $this->directory = $directory;
    }

    public function createTracer($class, ?string $label = null): string
    {
        $reflection = new \ReflectionClass($class);

        $functionTemplate = '
public function #name#(#parameters#) #returnType# {
    $e = $this->stopwatch->start(\'#eventname#\', \'section\');

    $result = $this->decorated->#name#(#callparams#);

    if ($e->isStarted()) $e->stop();
    #returnresult#
}
        ';

        if ($label === null) {
            $label = $reflection->getName();
        }
        $functions = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {

            if ($method->getName() == '__construct') {
                continue;
            }

            $parameters = [];
            $calls = [];

            foreach ($method->getParameters() as $param) {
                $type = '';
                if ($param->getClass()) {
                    $type = '\\' . $param->getClass()->getName();
                } else if ($param->getType()) {
                    $type = $param->getType()->getName();
                }

                $parameters[] = $type . ' $' . $param->getName();
                $calls[] = '$'. $param->getName();
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

            $functionLabel = $label . '.' . $method->getName();

            $functions[] = str_replace(
                ['#name#', '#parameters#', '#returnType#', '#eventname#', '#callparams#', '#returnresult#'],
                [$method->getName(), $parameters, $return, $functionLabel, $calls, $functionReturn],
                $functionTemplate
            );
        }

        $originalClass = $reflection->getName();
        $lastPart = explode('\\', $originalClass);
        $className = array_pop($lastPart) . 'Tracer';

        $functions = implode("\n", $functions);

        $template = str_replace(
            ['#className#', '#originalClass#', '#functions#'],
            [$className, '\\' . $originalClass, $functions],
            '<?php

namespace Shopware\Traceable\DependencyInjection\Tracer;

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
}
            '
        );

        file_put_contents($this->directory . '/' . $className . '.php', $template);

        return 'Shopware\\Traceable\\DependencyInjection\\Tracer\\' . $className;
    }
}