<?php
namespace SwaggerGen\Generator;

abstract class AbstractGenerator implements GeneratorInterface
{
    protected $namespace;
    protected $class_files = [];
    
    const NAMESPACE='';

    public function __construct(string $namespace, array $options, $more_specificity = false)
    {
        $this->namespace = $namespace;
        $this->build($options);
    }

    abstract protected function build(array $options);

    public function generate(array $options)  : array
    {
        $this->build($options);
        return $this->getGeneratedClassFiles();
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getGeneratedClassFiles(): array
    {
        return $this->class_files;
    }

    public function getGeneratedFilesCount(): int
    {
        return count($this->class_files);
    }

    protected function addClass(string $model, string $content ) {
        $this->class_files[$model] = [
            'namespace' => $this->getNamespace(),
            'dir' => preg_replace("/\\\\/", DIRECTORY_SEPARATOR ,$this->getNamespace()),
            'content' => $content
        ];
    }
}
