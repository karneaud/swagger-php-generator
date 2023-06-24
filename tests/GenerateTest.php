<?php

namespace SwaggerGen\Tests;

use SwaggerGen\Generate;
use PHPUnit\Framework\TestCase;
use SwaggerGen\Generator\GeneratorInterface;

class GenerateTest extends TestCase
{
    protected $generate;

    public function setUp():Void {
        $this->generate = new Generate('App', __DIR__ . '/Mocks/swagger.yaml', 'tests/data', false, null, null);
        
    }

    public function testInstanceOfGeneratorInterface()
    {
        $this->assertInstanceOf(GeneratorInterface::class, $this->generate->getGeneratedClassFiles()['request']);
    }

    public function testGetGeneratedFilesCount()
    {
        $this->assertGreaterThan(0, $this->generate->getGeneratedFilesCount());
    }

    public function testHasGeneratedClass() {
        $classes = $this->generate->getGeneratedClassFiles();
        $this->assertArrayHasKey('User',$models = $classes['model']->getGeneratedClassFiles());
        $this->assertStringContainsString( 'use App\Model\AbstractModel',$models['User']['content']);
        $this->assertTrue(file_exists("tests/data/".$models['User']['dir'] . "/User.php"));
    }

    function tearDown(): void {
       $this->__deleteDir('tests/data');
    }

    protected function __deleteDir($dir) {
        if (!is_dir($dir)) {
            return;
        }
    
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object)) {
                    $this->__deleteDir($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
        }
    
        rmdir($dir);
    }    
}
