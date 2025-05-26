<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use Nufat\Nudb\Nudb;

// tests/NudbTest.php
class NudbTest extends TestCase
{
    const TEST_WS_URL = 'wss://nudb.nufat.id';

    public function testConnection()
    {
        $nudb = new Nudb(self::TEST_WS_URL);
        $this->assertInstanceOf(Nudb::class, $nudb);
    }
public function testSetAndGet()
{
    $nudb = new Nudb(self::TEST_WS_URL);
    $path = 'test/' . uniqid();
    $expected = ['name' => 'Unit Test ' . uniqid()];
    
    $nudb->set($path, $expected);
    
    // Poll for data (max 3 attempts)
    $attempts = 0;
    do {
        $response = $nudb->get($path);
        if (!empty($response)) break;
        usleep(300000); // 300ms delay
        $attempts++;
    } while ($attempts < 3);
    
    $this->assertEquals($expected, $response);
}
    
    public function testGetCollection()
    {
        $nudb = new Nudb(self::TEST_WS_URL);
        $path = 'test';
        
        $response = $nudb->get($path);
        
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
    }
}