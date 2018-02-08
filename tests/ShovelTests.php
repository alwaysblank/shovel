<?php
namespace Murmur\Tools;

use \PHPUnit\Framework\TestCase;
use \Symfony\Component\Filesystem\Filesystem;
use \Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use \Zenodorus\Filesystem as ZenoFS;

class ShovelTest extends TestCase
{
    const TIMESTAMP = '1518044860';

    const TESTDIRS = [
        'tests/files/star',
        'tests/files/star/wars',
        'tests/files/star/trek',
        'tests/files/star/gate',
        'tests/files/john',
        'tests/files/john/wick',
        'tests/files/john/conner',
    ];

    const TESTFILES = [
        'tests/files/star/wars/millenium.falcon',
        'tests/files/star/trek/starship.enterprise',
        'tests/files/star/trek/deepspace.nine',
        'tests/files/john/conner/thumbs.up'
    ];

    protected function setupTestFiles()
    {
        $fs = new Filesystem();

        $fs->mkdir($this::TESTDIRS);
        $fs->touch($this::TESTFILES);
    }

    protected function teardownTestFiles()
    {
        $fs = new Filesystem();

        $fs->remove(array_merge($this::TESTFILES, $this::TESTDIRS));
    }

    public function testArchiveName()
    {
        $this->assertEquals(
            sprintf('source_%s.zip', $this::TIMESTAMP),
            Shovel::archiveName($this::TIMESTAMP),
            "Archive name does not match."
        );
    }
    
    public function testDeployName()
    {
        $this->assertEquals(
            sprintf('deploy_%s', date_format(date_create_from_format(
                'U', $this::TIMESTAMP
            ), 'Y.m.d.Gis')),
            Shovel::deployName($this::TIMESTAMP),
            "Deploy name does not match."
        );
    }

    public function testCreate()
    {
        $this->setupTestFiles();

        $timestamp = time();
        $Shovel = new Shovel($timestamp);
        $Shovel->create(
            ZenoFS::resolve(ZenoFS::slash(__DIR__, 'files')),
            ZenoFS::resolve(ZenoFS::slash(__DIR__))
        );

        $zip = new \ZipArchive;
        $archive = ZenoFS::slash(__DIR__, Shovel::archiveName($timestamp));
        $zip->open($archive);
        foreach ($this::TESTFILES as $file) {
            $bits = explode('/', $file);
            unset($bits[0], $bits[1]);
            $this->assertInternalType(
                "int",
                $zip->locateName(ZenoFS::slashAr($bits))
            );
        }
        $zip->close();
        unlink($archive);
        $this->teardownTestFiles();
    }
    
    public function testExtract()
    {
        // Set up
        $this->setupTestFiles();
        $Shovel = new Shovel($this::TIMESTAMP);
        $Shovel->create(
            ZenoFS::resolve(ZenoFS::slash(__DIR__, 'files')),
            ZenoFS::resolve(ZenoFS::slash(__DIR__))
        );
        $archive = ZenoFS::slash(__DIR__, Shovel::archiveName($this::TIMESTAMP));
        $Shovel->extract(
            $archive,
            ZenoFS::resolve(ZenoFS::slash(__DIR__, 'extract'))
        );

        foreach ($this::TESTFILES as $file) {
            $bits = explode('/', $file);
            unset($bits[0], $bits[1]);
            $bits = array_merge(
                [
                    __DIR__,
                    'extract',
                    Shovel::deployName($this::TIMESTAMP),
                ],
                $bits
            );
            $this->assertFileExists(ZenoFS::slashAr($bits));
        }
         
        // Tear down
        unlink($archive);
        ZenoFS::recursiveRemove(ZenoFS::slash(__DIR__, 'extract'));
        $this->teardownTestFiles();
    }
}
