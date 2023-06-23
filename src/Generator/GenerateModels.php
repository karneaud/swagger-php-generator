<?php

namespace SwaggerGen\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Property;
use Nette\PhpGenerator\PhpNamespace;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Yaml\Yaml;

class GenerateModels extends AbstractGenerator implements GeneratorInterface
{
    const MODEL_CLASS_NAME = 'Model';
    const NAMESPACE_MODEL = 'Model';

    public function __construct(string $namespace, array $options, $more_specificity = false)
    {
        $this->namespace = $namespace;
        $this->build($options);
    }
    
    static function getNamespaceModel(): string {
		return self::NAMESPACE_MODEL;
    }

    function namespaceModel(): string {
	    return self::getNamespaceModel();
    }
	
    public function build(array $api)
    {
        $namespaceName = $this->getNamespaceModel();

        $namespace = new PhpNamespace($namespaceName);

        foreach (($api['definitions'] ?? $api['components']['schemas']) as $className => $classDetails) {
            $class = new ClassType($className, $namespace);
            $class->setExtends("$namespaceName\\" . self::MODEL_CLASS_NAME);
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

            $this->classes[$className] = $class;
        }
    }

    private function classProperties(array $properties, ClassType $class, ?array $required): void
    {
        $converter = new CamelCaseToSnakeCaseNameConverter;
        $namespaceName = $this->getNamespaceModel();
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

    public function saveClasses(string $dir): void
    {
        $dir = $this->dirNamespace($dir, self::NAMESPACE_MODEL);
        $this->saveClassesInternal($dir, "{$this->namespace}\\" . $this->getNamespaceModel());
    }

    public function dumpParentClass(string $dir): void
    {
        $dir = $this->dirNamespace($dir, self::NAMESPACE_MODEL);
        $this->dumpParentInternal($dir, dirname(__DIR__) . '/Model/Model.php', "{$this->namespace}" . $this->getNamespaceModel());
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
