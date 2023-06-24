<?php

namespace SwaggerGen;

use RuntimeException;
use Nette\FileNotFoundException;
use Symfony\Component\Yaml\Yaml;
use SwaggerGen\Generator\GenerateModels;
use SwaggerGen\Generator\GenerateRequests;
#use SwaggerGen\Generator\GeneratorResponse;
use SwaggerGen\Generator\GeneratorInterface;

final class Generate
{
    public $saved_models = 0;
    public $saved_requests = 0;

    private $dir_path;
    private $namespace;
    private $generateDefaults = [
        'model' => true,
        'request' => true,
        'response' => false
    ];
    private $class_files = [];
    private $generateOptions = [];

    /**
     * Generates all required files into the specified directory
     *
     * @param string      $namespace
     * @param string      $yaml_file
     * @param string      $dir
     * @param bool        $more_specificity
     * @param string|null $generate_class_path
     * @param array       $additional_classes
     */
    public function __construct(
        string $namespace,
        string $yaml_file,
        string $dir,
        bool $more_specificity = false,
        ?string $generate_class_path = null,
        ?array $additional_classes = null
    ) {
	    $this->base_path = $dir;
        $this->namespace = $namespace;
        $this->generateOptions = is_null($additional_classes)? $this->generateDefaults : array_filter(array_merge($this->generateDefaults, $additional_classes));

        $yaml_options = preg_match("/\.json$/", $yaml_file)? (array) json_decode(file_get_contents($yaml_file), true) : Yaml::parse(file_get_contents($yaml_file),true);
        $generate_class_path = $generate_class_path ?? 'SwaggerGen\\Generator';

	    $this->generate( $namespace, $generate_class_path, $yaml_options, $more_specificity );
    }

    /**
     * Generates all required files using the specified generator class
     *
     * @param string $namespace
     * @param string $class_path
     * @param array $yaml_options
     * @param bool   $more_specificity
     */
    public function generate(string $namespace, string $class_path, array $yaml_options, bool $more_specificity = false)
    {
        foreach($this->generateOptions as $option => $value) {
            if($value) {
                $generator_class_name = $value === true? sprintf("Generate%ss" , ucfirst($option)) : $value; 
                $generator_class_name = "$class_path\\$generator_class_name";
                $generator = new $generator_class_name($namespace,$yaml_options,$more_specificity);
                $this->addClass($option, $generator);
            }
        }

        $this->saveClasses();
    }

    /**
     * @param string $type
     * @param SwaggerGen\Generator\GeneratorInterface $generator
     */
    private function addClass(string $type, GeneratorInterface $generator)
    {
        $this->class_files[$type] = $generator;
    }
    function getGeneratedClassFiles(): array {
        return $this->class_files;
    }

    function getGeneratedFilesCount(): int
    {
        $totalCount = 0;
        foreach ($this->class_files as $item) {
            $totalCount += $item->getGeneratedFilesCount();
        }

        return $totalCount;
    }

	/**
	 * Saves generated classes down as PHP files
     * 
	 * @throws RuntimeException
	 * @throws FileNotFoundException
	 */
	protected function saveClasses(): void{
		if (empty($this->class_files)){
			throw new RuntimeException('No classes were created, try running the generate() method first');
		}

		foreach ($this->class_files as $type => $generator) {
            $class_files = $generator->getGeneratedClassFiles();
            foreach($class_files as $model_name => $class)
			{
                $dir = $this->checkDir(sprintf("%s%s%s",$this->base_path,DIRECTORY_SEPARATOR,$class['dir']));
                file_put_contents(sprintf("%s%s%s.php",$dir,DIRECTORY_SEPARATOR,$model_name), $class['content']);
            }
        }
	}
	/**
	 * @param string $dir
	 * @return string
	 * @throws FileNotFoundException
	 */
	private function checkDir(string $dir): string{
		if (!file_exists($dir)){
			mkdir($dir, 0775, true);
		}
		if (!file_exists($dir)){
			throw new FileNotFoundException("The directory $dir did not exist and could not be created");
		}

		return $dir;
	}
}
