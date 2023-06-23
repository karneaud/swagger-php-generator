<?php
namespace SwaggerGen\Generator;

use RuntimeException;

abstract class AbstractGenerator implements GeneratorInterface
{
    protected $namespace;
    protected $class_files = [];

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

}
