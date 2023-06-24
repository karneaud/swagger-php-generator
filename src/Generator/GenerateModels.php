<?php

namespace SwaggerGen\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Property;
use Nette\PhpGenerator\PhpNamespace;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Yaml\Yaml;

class GenerateModels extends AbstractGenerator implements GeneratorInterface
{
    const CLASS_NAME = 'AbstractModel';
    const NAMESPACE = 'Model';

    public function __construct(string $namespace, array $options, $more_specificity = false)
    {
        $this->namespace = $namespace;
        $this->build($options);
    }
    
    function getNamespace(): string {
		return "{$this->namespace}\\" . self::NAMESPACE;
    }

    function namespaceModel(): string {
	    return $this->getNamespace();
    }
	
    public function build(array $api)
    {
        $namespaceName = $this->namespaceModel();

        $namespace = new PhpNamespace($namespaceName);

        foreach (($api['definitions'] ?? $api['components']['schemas']) as $className => $classDetails) {
            $class = new ClassType($className, $namespace);
            $class->setExtends("$namespaceName\\" . self::CLASS_NAME);
            $class->addComment('** This file was generated automatically, you might want to avoid editing it **');
            if (!empty($classDetails['description'])) {
                $class->addComment("\n" . $classDetails['description']);
            }

            if (isset($classDetails['allOf'])) {
                $parentClassName = $this->typeFromRef($classDetails['allOf'][0]);
                $class->setExtends("$namespaceName\\$parentClassName");
                $required = $classDetails['allOf'][1]['required'];
                $properties = $classDetails['allOf'][1]['properties'];
            } else {
                $required = $classDetails['required'] ?? null;
                $properties = $classDetails['properties'] ?? null;
            }

            $this->classProperties($properties, $class, $required);
            $php_file = (string)$class; 
            $use = "{$this->getNamespace()}\\" . self::CLASS_NAME;
			$php_file = "<?php\nnamespace {$this->getNamespace()};\nuse $use;\n$php_file";
			    
            $this->addClass($className,$php_file);
        }
        // add template
        $this->addClass('AbstractModel',file_get_contents(dirname(__DIR__) . '/Template/Model/Model.php'));
    }

    private function classProperties(array $properties, ClassType $class, ?array $required): void
    {
        $converter = new CamelCaseToSnakeCaseNameConverter;
        $namespaceName = $this->namespaceModel();
        if (is_null($required)) {
            $required = [];
        }

        foreach ($properties as $propertyName => $propertyDetails) {
            if (isset($propertyDetails['$ref'])) {
                $type = $this->typeFromRef($propertyDetails);
                $typeHint = "$namespaceName\\$type";
            } else {
                $type = $propertyDetails['type'];
                $typeHint = $type;
            }

            $property = $class->addProperty($propertyName)->setVisibility('protected');
            $property->addComment($propertyDetails['description'] ?? "\n");

            if ($type === 'array') {
                $subType = $this->typeFromRef($propertyDetails['items']);
                $commentType = "{$subType}[]";
                if (isset($propertyDetails['items']['$ref'])) {
                    $subTypeHint = "$namespaceName\\$subType";
                } else {
                    $subTypeHint = $subType;
                }
            } else {
                $commentType = $type;
                $subType = $subTypeHint = '';
            }
            if ($commentType === 'number') {
                $commentType = 'float';
            }

            $property->addComment("@var $commentType");
            
            if (in_array($propertyName, $required, true)) {
                $property->addComment('@required');
            } else {
                $this->blankValue($property, $type);
            }

            $capitalCase = $converter->denormalize($propertyName);
            $capitalCase = ucfirst($capitalCase);
            $class->addMethod('get' . $capitalCase)
                ->setBody("return \$this->$propertyName;")
                ->addComment("@return $commentType");

            $setter = $class->addMethod('set' . $capitalCase)
                ->setBody("\$this->$propertyName = \$$propertyName;\n\nreturn \$this;")
                ->addComment("@param $commentType \$$propertyName")
                ->addComment('')
                ->addComment('@return $this');

            $setParameter = $setter->addParameter($propertyName);
            if ($this->notScalarType($type)) {
                $setParameter->setTypeHint($typeHint);
            }

            if ($subType) {
                $propertyNameSingular = $this->unPlural($propertyName);
                $capitalCaseSingular = $this->unPlural($capitalCase);

                $addTo = $class->addMethod('add' . $capitalCaseSingular)
                    ->setBody("\$this->{$propertyName}[] = \$$propertyNameSingular;\n\nreturn \$this;")
                    ->addComment("@param $subType \$$propertyNameSingular")
                    ->addComment('')
                    ->addComment('@return $this');

                $setParameter = $addTo->addParameter($propertyNameSingular);
                if ($this->notScalarType($subType)) {
                    $setParameter->setTypeHint($subTypeHint);
                }
            }
        }
    }

    /**
	 * @param string $type
	 * @return bool
	 */
	protected function notScalarType(string $type): bool{
		return !in_array($type, ['integer', 'string', 'boolean', 'number']);
	}

    private function blankValue(Property $property, string $type): void
    {
        if ($type !== 'array' && $this->notScalarType($type)) {
            return;
        }

        switch ($type) {
            case 'array':
                $property->setValue([]);
                break;
            case 'string':
                $property->setValue('');
                break;
            case 'integer':
                $property->setValue(0);
                break;
            case 'number':
                $property->setValue(0.0);
                break;
            case 'boolean':
                $property->setValue(false);
                break;
            default:
                throw new RuntimeException("The property with name {$property->getName()} and type $type was not recognised to set a default value");
        }
    }

    
}
