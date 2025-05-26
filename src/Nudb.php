<?php
namespace Nufat\Nudb;

use Exception;
use WebSocket\Client;

class Nudb
{
    protected $client;
    protected $headers = [];

    // In src/Nudb.php
public function __construct($url, $headers = [])
{
    $this->headers = $headers;
    
    try {
        $this->client = new Client($url, [
            'headers' => $headers,
            'timeout' => 30,  // Increased from 5 to 30 seconds
            'persistent' => true
        ]);
    } catch (Exception $e) {
        throw new Exception("Connection failed: " . $e->getMessage());
    }
}

protected function sendMessage($message)
{
    $json = json_encode($message);
    error_log("Sending: " . $json);  // Add this line
    $this->client->send($json);
}

// In src/Nudb.php
protected function receiveMessage()
{
    try {
        $this->client->setTimeout(10); // 10 second timeout
        $response = $this->client->receive();
        
        if (empty($response)) {
            throw new Exception("Empty server response");
        }
        
        return $response;
    } catch (Exception $e) {
        throw new Exception("WebSocket error: " . $e->getMessage());
    }
}
    // src/Nudb.php
public function get($path)
{
    $this->sendMessage([
        "type" => "get",
        "path" => $path,
        "headers" => $this->headers
    ]);

    $response = $this->receiveMessage();
    $data = json_decode($response, true);
    
    // Handle the actual server response format
    return $data['data'] ?? $data; // Server returns either {"data":...} or direct value
}

public function set($path, $data)
{
    $this->sendMessage([
        "type" => "set",
        "path" => $path,
        "data" => $data,
        "headers" => $this->headers
    ]);
    
    // Server doesn't currently respond to set operations
    // So we don't wait for a response
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