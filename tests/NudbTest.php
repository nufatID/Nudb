<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Nudb\Nudb;

class NudbTest extends TestCase
{
    public function testConnection()
    {
        $nudb = new Nudb("wss://nudb.bungtemin.net");
        $this->assertInstanceOf(Nudb::class, $nudb);
    }

    public function testSetAndGet()
{
    $nudb = new Nudb("wss://nudb.bungtemin.net");

    try {
        $nudb->set("test/123", ["name" => "Unit Test"]);
        $data = $nudb->get("test/123");
        $this->assertEquals("Unit Test", $data["name"] ?? null);
    } catch (\Exception $e) {
        $this->fail("Exception thrown during test: " . $e->getMessage());
    }
}
}