<?php

namespace SwaggerGen;

use Symfony\Component\Yaml\Yaml;
use SwaggerGen\Generator\GeneratorModel;
use SwaggerGen\Generator\GeneratorRequest;
use SwaggerGen\Generator\GeneratorResponse;

class Generate
{
    public $saved_models = 0;
    public $saved_requests = 0;

    private $dir_path;
    private $generateDefaults = [
        'model' => true,
        'request' => true,
        'response' => true
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
	$yaml_options = preg_match("/\.json$", $yaml_file)? (array) json_decode(file_get_contents($yaml_file), true) : Yaml::parse(file_get_contents($yaml_file),true);
        $this->base_path = $dir;
        $this->generateOptions = array_filter(array_merge($this->generateDefaults, $additional_classes));

        $generate_class_path = $generate_class_path ?? 'SwaggerGen\Generator';

	$this->generate( $namespace, $generate_class_path, $yaml_options, $more_specificity );
    }

    /**
     * Generates all required files using the specified generator class
     *
     * @param string $namespace
     * @param string $class_path
     * @param string $yaml_options
     * @param bool   $more_specificity
     */
    public function generate(string $namespace, string $class_path, string $yaml_options, bool $more_specificity = false)
    {
        foreach($this-generateOptions as $option => $value) {
		$generator_class_name = $value === true? "Generate{capitalize($option)}" : $value; 
		$generator_class_name = "$class_path\\$generator_class_name";
		$generator = new $generator_class_name($namespace,$yaml_options,$more_specificty);
		$this->addClass($option, $generator);
	}
    }

    /**
     * @param string $type
     * @param SwaggerGen\Generator\GeneratorInterface $generator
     */
    private function addClass(string $type, GeneratorInterface $generator)
    {
        $this->class_fles[$type] = $generator;
    }

    /**
     * Generates model classes based on the provided YAML file
     *
     * @param array  $yamlData
     * @param string $namespace
     * @param bool $specificty
     */
    protected function generateModelClasses(string $namespace, $yaml_data, $specificity = false)
    {
        $this->addClass('model' , new GenerateModels($namespace, $yaml_data, $specificity ));
    }

    /**
     * Generates request classes based on the provided YAML file
     *
     * @param array  $yamlData
     * @param string $namespace
     * @param string $dir
     * @param bool   $specificity
     */
    protected function generateRequestClasses(string $namespace,array $yaml_data, bool $specificity = false)
    {
        $this->addClass('request' , new GenerateModels($namespace, $yaml_data, $specificity ));
    }

    /**
     * Generates response classes based on the provided YAML file
     *
     * @param array  $yamlData
     * @param string $namespace
     * @param bool $specificity
     */
    protected function generateResponseClasses(string $namespace, array $yaml_data, bool $specificity = false)
    {
        $generator = new GenerateResponses($namespace, $yaml_data, $specificity);
        $this->addClass('response',$generator);
    }
}
