<?php

namespace SwaggerGen\Tests;

use SwaggerGen\Generate;
use PHPUnit\Framework\TestCase;
use SwaggerGen\Generator\GeneratorInterface;

class GenerateTest extends TestCase
{
    protected $generate;

    public function setUp():Void {
        $this->generate = new Generate('App\Models', __DIR__ . '/Mocks/swagger.yaml', 'tests/data', false, null, null);
        
    }

    public function testInstanceOfGeneratorInterface()
    {
        $this->assertInstanceOf(GeneratorInterface::class, $this->generate->getGeneratedClassFiles()['request']);
    }

    public function testGetGeneratedFilesCount()
    {
        $this->assertGreaterThan(0, $this->generate->getGeneratedFilesCount());
    }
}
