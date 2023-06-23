<?php
namespace SwaggerGen\Generator;

use RuntimeException;

abstract class AbstractGenerator implements GeneratorInterface
{
    protected $namespace;
    protected $class_files = [];

    public function __construct(string $namespace, array $options)
    {
        $this->namespace = $namespace;
        $this->build($options);
    }

    abstract protected function build(array $options);

    public function generate(array $options)  : array
    {
        return $this->build($options);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getGeneratedClassFiles(): array
    {
        return $this->class_files;
    }

    public function getGeneratedFileCount(): int
    {
        return count($this->class_files);
    }

}
