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
        'tmp/files/star',
        'tmp/files/star/wars',
        'tmp/files/star/trek',
        'tmp/files/star/gate',
        'tmp/files/john',
        'tmp/files/john/wick',
        'tmp/files/john/conner',
    ];

    const TESTFILES = [
        'tmp/files/star/wars/millenium.falcon',
        'tmp/files/star/trek/starship.enterprise',
        'tmp/files/star/trek/deepspace.nine',
        'tmp/files/john/conner/thumbs.up'
    ];

    protected function setUp()
    {
        $fs = new Filesystem();

        $fs->mkdir($this::TESTDIRS);
        $fs->touch($this::TESTFILES);
    }

    protected function tearDown()
    {
        ZenoFS::recursiveRemove(ZenoFS::slash(__DIR__, '..', 'tmp'));
    }

    public function testArchiveName()
    {
        $this->assertEquals(
            sprintf('source_%s.zip', date_format(date_create_from_format(
                'U',
                $this::TIMESTAMP
            ), Shovel::TIME_FORMAT)),
            Shovel::archiveName($this::TIMESTAMP),
            "Archive name does not match."
        );
    }
    
    public function testDeployName()
    {
        $this->assertEquals(
            sprintf('deploy_%s', date_format(date_create_from_format(
                'U',
                $this::TIMESTAMP
            ), Shovel::TIME_FORMAT)),
            Shovel::deployName($this::TIMESTAMP),
            "Deploy name does not match."
        );
    }

    public function testCreate()
    {
        $timestamp = time();
        $Shovel = new Shovel($timestamp);
        $Shovel->create(
            ZenoFS::resolve(ZenoFS::slash(__DIR__, '..', 'tmp', 'files')),
            ZenoFS::resolve(ZenoFS::slash(__DIR__, '..', 'tmp'))
        );

        $zip = new \ZipArchive;
        $archive = ZenoFS::slash(__DIR__, '..', 'tmp', $Shovel->currentArchiveName());
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
    }
    
    public function testExtract()
    {
        $Shovel = new Shovel($this::TIMESTAMP);
        $Shovel->create(
            ZenoFS::resolve(ZenoFS::slash(__DIR__, '..', 'tmp', 'files')),
            ZenoFS::resolve(ZenoFS::slash(__DIR__, '..', 'tmp'))
        );
        $archive = ZenoFS::slash(__DIR__, '..', 'tmp', $Shovel->currentArchiveName());
        $Shovel->extract(
            $archive,
            ZenoFS::resolve(ZenoFS::slash(__DIR__, '..', 'tmp', 'extract'))
        );

        foreach ($this::TESTFILES as $file) {
            $bits = explode('/', $file);
            unset($bits[0], $bits[1]);
            $bits = array_merge(
                [
                    __DIR__,
                    '..',
                    'tmp',
                    'extract',
                    $Shovel->currentDeployName(),
                ],
                $bits
            );
            $this->assertFileExists(ZenoFS::slashAr($bits));
        }
    }
}
