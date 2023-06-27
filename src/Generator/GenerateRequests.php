<?php

namespace SwaggerGen\Generator;

use Nette\InvalidArgumentException;
use Nette\FileNotFoundException;
use Nette\PhpGenerator\ClassType;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class GenerateRequests extends AbstractGenerator implements GeneratorInterface
{
    const CLASS_NAME = 'Request';
    const NAMESPACE = 'Message\\Request';

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

	/**
	 * Utility function
	 *
	 * @param string $string
	 * @param string $char
	 * @return string
	 */
	protected function stringNotEndWith(string $string, string $char): string{
		return $string[strlen($string)-1]===$char ? substr($string, 0, -1) : $string;
	}

    protected function build(array $api)
    {
	
        if(!is_null(($b = $api['basePath'] ?? (isset($api['servers']) ? $api['servers'][0]['url'] : null)))) {
            $base_uri = $this->stringNotEndWith($b, '/');
        }

        foreach ($api['paths'] as $path => $path_details) {
            if ($this->more_specificity) {
                $adapted_path = $this->pathWithParamsNoPlaceholders($path);
            } else {
                $adapted_path = $this->pathNoParams($path);
            }
            $path_camel = $this->pathToCamelCase($adapted_path);

            foreach ($path_details as $method => $method_details) {
                $class_name = ucfirst($method) . $path_camel;
                $class = new ClassType($class_name);
                $class->setExtends(self::CLASS_NAME);
                $class->addComment($method_details['summary'] ?? ' ');

                $uri = empty($base_uri) ? $path : "{$base_uri}/{$path}";
                $class->addConstant('URI', $uri);
                $class->addConstant('METHOD', strtoupper($method));

                $this->handleParams($method_details, $class);
                $this->handleResponse($method_details, $class);
                $php_file = (string)$class; 
                $use = "{$this->getNamespace()}\\" . self::CLASS_NAME;
                $php_file = "<?php\nnamespace {$this->getNamespace()};\nuse $use;\n$php_file";
                
                $this->addClass($class_name,$php_file);
            }
        }
    }

    private function pathToCamelCase(string $path): string
    {
        $path = str_replace('/', '-', $path);
        $path_arr = explode('-', $path);
        foreach ($path_arr as &$item) {
            $item = ucfirst($item);
        }
        unset($item);
        $path = implode('', $path_arr);

        return $path;
    }

    private function pathNoParams(string $path): string
    {
        $path = substr($path, 1);
        $path_arr = explode('/', $path);
        $path_no_params = [];
        foreach ($path_arr as $item) {
            if (strpos($item, '{') !== false) {
                continue;
            }
            $path_no_params[] = $item;
        }
        $path = implode('/', $path_no_params);

        return $path;
    }

    private function pathWithParamsNoPlaceholders(string $path)
    {
        $path = substr($path, 1);
        $path = explode('/', $path);
        $path_with_params = [];
        foreach ($path as $item) {
            $item = str_replace('{', '', $item);
            $item = str_replace('}', '', $item);
            $path_with_params[] = $item;
        }
        $path = implode('/', $path_with_params);

        return $path;
    }

    private function handlePathParams(array $path_params, ClassType $class): void
    {
        if (empty($path_params)) {
            return;
        }

        $constructor = $class->addMethod('__construct');
        $param_names = [];

        foreach ($path_params as $path_param) {
            $param_name = $path_param['name'];
            $param_names[] = $param_name;
            
            if ($path_param['required']) {
                $constructor->addParameter($param_name);
            } else {
                $constructor->addParameter($param_name, null);
            }

            $path_param['type'] = $path_param['type'] ?? $path_param['schema']['type'];
            $class->addProperty($param_name)
                ->addComment($path_param['description'] ?? ' ')
                ->addComment('')
                ->addComment("@var {$path_param['type']}");

            $constructor->addBody("\$this->$param_name = \$$param_name;");
        }

        if (!empty($param_names)) {
            $class->addProperty('path_params')
                ->setStatic(true)
                ->setValue($param_names)
                ->setVisibility('protected');
        }
    }

    function namespaceRequest() {
	    return $this->getNamespace();
    }

   function namespaceModel() {
	   return GenerateModels::NAMESPACE;
   }

    private function handleQueryParams(array $query_params, ClassType $class): void
    {
        if (empty($query_params)) {
            return;
        }

        $param_names = [];
        foreach ($query_params as $query_param) {
            $param_name = $query_param['name'];
            $param_names[] = $param_name;

            if (isset($query_param['required'])) {
                $method = $class->getMethod('__construct');
            } else {
                $method = $class->addMethod('set' . $this->pathToCamelCase($query_param['name']));
            }

            $method->addParameter($param_name);
            $query_param['type'] =  $query_param['type'] ?? $query_param['schema']['type'];
            $class->addProperty($param_name)
                ->addComment($query_param['description'])
                ->addComment('')
                ->addComment("@var {$query_param['type']}");

            $method->addBody("\$this->$param_name = \$$param_name;");
        }

        if (!empty($param_names)) {
            try {
                $query_params_property = $class->getProperty('query_params');
                $query_params_array = $query_params_property->getValue();
            } catch (InvalidArgumentException $e) {
                $query_params_property = $class->addProperty('query_params')
                    ->setStatic(true)
                    ->setVisibility('protected');
                $query_params_array = [];
            }

            $value = array_merge($query_params_array, $param_names);
            $query_params_property->setStatic(true)
                ->setValue($value)
                ->setVisibility('protected');
        }
    }

    protected function handleParams(array $method_details, ClassType $class): void
    {
        $parameters = $method_details['parameters'] ?? [];
        $path_params = $query_params = [];

        foreach ($parameters as $parameter) {
            switch ($parameter['in']) {
                case 'path':
                    $path_params[] = $parameter;
                    break;
                case 'query':
                    $query_params[] = $parameter;
                    break;
                case 'body':
                    $this->handleBodyParam($parameter, $class);
                    break;
            }
        }

        $this->handlePathParams($path_params, $class);
        $this->handleQueryParams($query_params, $class);
    }

    private function handleBodyParam(array $parameter, ClassType $class): void
    {
        $schema = $parameter['schema'];
        if (empty($schema)) {
            return;
        }

        $type = $this->typeFromRef($schema);
        $name = strtolower($parameter['name']);
        $model_ns = $this->namespaceModel();
        $model_class = "{$this->namespace}\\$model_ns\\$type";

        $class->addMethod('setBody')
            ->addComment("@param $model_class \$$name")
            ->setBody("\$this->body = json_encode(\$$name);")
            ->addParameter($name)
            ->setTypeHint($model_class);
    }

    protected function handleResponse(array $method_details, ClassType $class): void
    {
        $response_models = [];
        $model_ns = "{$this->namespace}\\" . $this->namespaceModel();
        $has_2xx = false;

        foreach ($method_details['responses'] as $code_string => $method) {
            $method['$ref'] = $method['$ref'] ?? (isset($method['content'])? ($method['content']['application/json']['schema']['$ref'] ?? null) : null); 
            $get_type_from = is_null($method['$ref']) ? 
                $method : ($method['schema'] ?? (isset($method['content'])? ($method['content']['application/json']['schema'] ?? null) : null) ) ;

            if (!is_null($get_type_from)) {
                $get_type_from['type'] = $get_type_from['type'] ?? $get_type_from['content']['application/json']['schema']['type'] ?? null;
                if ($get_type_from['type'] === 'array') {
                    $type = $this->typeFromRef($get_type_from['items'] ?? $get_type_from['content']['application/json']['schema']['items']);
                } else {
                    $type = $this->typeFromRef($get_type_from);
                }

                if ($this->notScalarType($type)) {
                    $type = "\\$model_ns\\$type::class";
                } else {
                    $type = "''";
                }
            } else {
                $type = "''";
            }

            $response_models[] = "'$code_string' => '$type'";

            if ((int)$code_string > 0 && (int)$code_string < 400) {
                $has_2xx = true;
            }
        }

        if (!$has_2xx) {
            throw new InvalidArgumentException('Response blocks must contain at least one positive response type');
        }

        $response_models = implode(",\n\t", $response_models);
        $response_models = "[\n\t$response_models\n]";

        $class->addMethod('sendWith')
            ->addBody("return \$client->make(\$this, $response_models);")
            ->addComment('@param Client $client')
            ->addComment('@return Model')
            ->addParameter('client')
            ->setTypeHint('Client');
        
    }

    /**
	 * Changes a Swagger definition into a type
	 *
	 * @param array $property
	 * @return string
	 */
	protected function typeFromRef(array $property): string{
        if (empty($property['$ref'])){
            return $property['type'] ?? (isset($property['content'])? $property['content']['application/json']['schema']['type'] : ' ');
		}

		return preg_replace('/(#\/definitions\/|#\/components\/schemas\/)/', '', $property['$ref']);
	}

    /**
	 * @param string $type
	 * @return bool
	 */
	protected function notScalarType(string $type): bool{
		return !in_array($type, ['integer', 'string', 'boolean', 'number']);
	}

}
