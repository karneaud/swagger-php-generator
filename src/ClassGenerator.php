<?php


namespace SwaggerGen;


use Nette\PhpGenerator\ClassType;

abstract class ClassGenerator {
	const REQUEST_CLASS_NAME = 'SwaggerRequest';
	const MODEL_CLASS_NAME = 'SwaggerModel';

	const NAMESPACE_MODEL = 'Models';
	const NAMESPACE_REQUEST = 'Requests';

	/**
	 * @var string
	 */
	private $namespace_name;

	/**
	 * @var ClassType[]
	 */
	public $classes = [];

	/**
	 * Creates a generator for a specific namespace
	 *
	 * @param string $namespace_name
	 */
	public function __construct(string $namespace_name){
		$this->namespace_name = $this->stringNotEndWith($namespace_name, '\\');
	}

	protected function namespaceModel(){
		return "{$this->namespace_name}\\".self::NAMESPACE_MODEL;
	}

	protected function namespaceRequest(){
		return "{$this->namespace_name}\\".self::NAMESPACE_REQUEST;
	}

	abstract public function saveClasses(string $dir);

	/**
	 * Saves generated classes down as PHP files
	 *
	 * @param string $dir
	 * @param string $namespace_name
	 * @throws \Exception
	 */
	protected function saveClassesInternal(string $dir, $namespace_name){
		if (empty($this->classes)){
			throw new \Exception("No classes were created, try running the generate() method first");
		}

		$dir = $this->checkDir($dir);

		foreach ($this->classes as $class_name => $class){
			$use = '';

			$php_file = (string)$class;
			$php_file = "<?php\nnamespace $namespace_name;\n$use\n$php_file\n";
			file_put_contents("{$dir}/{$class_name}.php", $php_file);
		}
	}

	abstract public function dumpParentClass(string $dir);

	/**
	 * @param string $dir
	 * @param string $file
	 * @param string $namespace
	 * @throws \Exception
	 */
	protected function dumpParentInternal(string $dir, string $file, string $namespace){
		$dir = $this->checkDir($dir);

		$content = file_get_contents($file);
		$content = str_replace("\nnamespace ".__NAMESPACE__.";", "\nnamespace {$namespace};", $content);
		$file_name = basename($file);
		file_put_contents("$dir/$file_name", $content);
	}

	/**
	 * Utility function
	 *
	 * @param string $string
	 * @param string $char
	 * @return string
	 */
	protected function stringNotEndWith(string $string, string $char){
		return $string[strlen($string)-1]===$char ? substr($string, 0, -1) : $string;
	}

	/**
	 * Changes a Swagger definition into a type
	 *
	 * @param array $property
	 * @return string
	 */
	protected function typeFromRef(array $property){
		if (!isset($property['$ref'])){
			return $property['type'];
		}

		return str_replace('#/definitions/', '', $property['$ref']);
	}

	/**
	 * @param string $dir
	 * @param string $namespace
	 * @return string
	 */
	protected function dirNamespace(string $dir, string $namespace){
		$dir = $this->stringNotEndWith($dir, '/');

		return "$dir/$namespace";
	}

	/**
	 * @param string $dir
	 * @return string
	 * @throws \Exception
	 */
	private function checkDir(string $dir){
		if (!file_exists($dir)){
			mkdir($dir, 0775, true);
		}
		if (!file_exists($dir)){
			throw new \Exception("The directory $dir did not exist and could not be created");
		}

		return $dir;
	}
}
