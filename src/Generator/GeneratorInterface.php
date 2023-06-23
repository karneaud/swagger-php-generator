<?php

namespace SwaggerGen\Generator;

interface GeneratorInterface
{
  
    /**
     * Builds the generator based on the provided options
     *
     * @param array $options
     */
    public function generate(array $options);
    /**
     * Returns the namespace used by the generator
     *
     * @return string
     */
    public function getNamespace(): string;

    /**
     * Returns the array of generated class files
     *
     * @return array
     */
    public function getGeneratedClassFiles(): array;

    /**
     * Returns the count of generated files
     *
     * @return int
     */
    public function getGeneratedFilesCount(): int;
}
