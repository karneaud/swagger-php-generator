<?php

namespace SwaggerGen\Generator;

use Nette\InvalidArgumentException;
use Nette\FileNotFoundException;
use Nette\PhpGenerator\ClassType;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class GenerateResponses extends AbstractGenerator implements GeneratorInterface
{
    const CLASS_NAME = 'Response';
    const NAMESPACE = 'Message\\Response';

    protected $mode_specificity = true;

    public function __construct(string $namespace, array $options, bool $more_specificity = false)
    {
        $this->namespace = $namespace;
        $this->more_specificity = $more_specificity;
        $this->build($options);
    }

    function getNamespace() : string {
        return "{$this->namespace}\\" . self::NAMESPACE;
    }
    
    protected function build(array $api)
    {
	
        foreach ($api['paths'] as $path => $path_details) {
                $path = array_reduce(
                        explode('/',$path),
                        fn($x,$y) => $x . ucfirst(preg_replace('/[^a-zA-Z]+/','',$y))
                    ,'');;
                foreach($path_details as $method => $info) {
                    $class_name = sprintf("%s%sResponse",ucfirst($method),$path);
                    $file_content = <<<PHP
                    <?php
                    namespace {$this->getNamespace()};

                    use Psr\Http\Message\ResponseInterface;
                    
                    class {$class_name} implements ResponseInterface {
                        
                        private \$request;
                        private \$data;
                        private \$code;
                        private \$message;

                        function __construct(\$data, \$request,int \$code = 0, string \$message = null) {
                            \$this->data = \$data;
                            \$this->request = \$request;
                            \$this->code = \$code;
                            \$this->message = \$message;
                        }

                        function getStatusCode() {
                            return \$this->code;
                        }

                        function withStatus(int \$code,string \$reason): ResponseInterface {
                            return \$this;
                        }

                        function getReasonPhrase(): string {
                            return \$this->message;
                        }
                    }
                    ?>
                    PHP; 
                    $this->addClass($class_name,$file_content);
                }

        }
    }
    
}
