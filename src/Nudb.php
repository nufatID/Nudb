<?php
namespace Nudb;

use Exception;
use RuntimeException;

class Nudb
{
    protected $host;
    protected $port;
    protected $path;
    protected $headers = [];
    protected $socket;

    public function __construct($url, $headers = [])
    {
        $parts = parse_url($url);
        $this->host = $parts['host'];
        $this->port = $parts['port'] ?? 80;
        $this->path = $parts['path'] ?? "/";
        $this->headers = $headers;

        $this->connect();
    }

    protected function connect()
    {
        $key = base64_encode(random_bytes(16));

        $header = "GET {$this->path} HTTP/1.1\r\n";
        $header .= "Host: {$this->host}:{$this->port}\r\n";
        $header .= "Upgrade: websocket\r\n";
        $header .= "Connection: Upgrade\r\n";
        $header .= "Sec-WebSocket-Key: $key\r\n";
        $header .= "Sec-WebSocket-Version: 13\r\n";

        foreach ($this->headers as $k => $v) {
            $header .= "$k: $v\r\n";
        }
        $header .= "\r\n";

        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if (!$this->socket) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }

        fwrite($this->socket, $header);
        fread($this->socket, 2048); // skip response headers
    }

    protected function sendFrame(string $payload)
    {
        $frame = chr(0x81); // FIN + text
        $len = strlen($payload);
        $maskBit = 0x80;

        if ($len <= 125) {
            $frame .= chr($maskBit | $len);
        } elseif ($len <= 65535) {
            $frame .= chr($maskBit | 126) . pack("n", $len);
        } else {
            $frame .= chr($maskBit | 127) . pack("J", $len);
        }

        $mask = random_bytes(4);
        $masked = '';
        for ($i = 0; $i < $len; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }

        $frame .= $mask . $masked;
        fwrite($this->socket, $frame);
    }

    private function receiveFrame()
{
    $firstByte = fread($this->socket, 1);

    if ($firstByte === false || strlen($firstByte) === 0) {
        throw new \RuntimeException("WebSocket connection closed unexpectedly (no frame header).");
    }

    $firstByte = ord($firstByte);
    $secondByte = ord(fread($this->socket, 1));
    $masked = ($secondByte >> 7) & 1;
    $len = $secondByte & 0x7F;

    if ($len === 126) {
        $ext = fread($this->socket, 2);
        if (strlen($ext) < 2) {
            throw new \RuntimeException("Invalid extended payload length (126).");
        }
        $len = unpack("n", $ext)[1];
    } elseif ($len === 127) {
        $ext = fread($this->socket, 8);
        if (strlen($ext) < 8) {
            throw new \RuntimeException("Invalid extended payload length (127).");
        }
        $len = unpack("J", $ext)[1]; // For 64-bit platforms
    }

    if ($len <= 0) {
        throw new \RuntimeException("Invalid frame length: $len");
    }

    if ($masked) {
        $mask = fread($this->socket, 4);
        if (strlen($mask) < 4) {
            throw new \RuntimeException("Failed to read masking key.");
        }

        $data = fread($this->socket, $len);
        if (strlen($data) < $len) {
            throw new \RuntimeException("Incomplete frame data.");
        }

        $result = '';
        for ($i = 0; $i < $len; $i++) {
            $result .= $data[$i] ^ $mask[$i % 4];
        }

        return $result;
    } else {
        $data = fread($this->socket, $len);
        if (strlen($data) < $len) {
            throw new \RuntimeException("Incomplete unmasked frame data.");
        }
        return $data;
    }
}

    protected function sendMessage($message)
    {
        $json = json_encode($message);
        $this->sendFrame($json);
    }

    public function get($path)
    {
        $this->sendMessage([
            "type" => "get",
            "path" => $path,
            "headers" => $this->headers
        ]);

        return json_decode($this->receiveFrame(), true);
    }

    public function set($path, $data)
    {
        $this->sendMessage([
            "type" => "set",
            "path" => $path,
            "data" => $data,
            "headers" => $this->headers
        ]);
    }

    public function update($path, $data)
    {
        $this->sendMessage([
            "type" => "update",
            "path" => $path,
            "data" => $data,
            "headers" => $this->headers
        ]);
    }

    public function delete($path)
    {
        $this->sendMessage([
            "type" => "delete",
            "path" => $path,
            "headers" => $this->headers
        ]);
    }

    public function push($path, $data)
    {
        $id = $this->generateId();
        $this->set("$path/$id", $data);
        return $id;
    }

    protected function generateId()
    {
        return time() . bin2hex(random_bytes(7));
    }
}