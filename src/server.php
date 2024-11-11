<?php
// Server settings
$server_ip = '192.168.200.150';
$server_port = 1200;
$maxClients = 4;
$connectedClients = [];
$removedClients = [];
$requestLogFile = "log.txt";
$timeout = 10000000000000;
$adminPassword = "123";

// Create and bind UDP socket
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    die("Couldn't create socket: " . socket_strerror(socket_last_error()));
}
if (!socket_bind($socket, $server_ip, $server_port)) {
    die("Couldn't bind socket: " . socket_strerror(socket_last_error()));
}
echo "The UDP server is listening on $server_ip:$server_port\n";

// Queues for requests
$adminRequests = [];
$regularRequests = [];

while (true) {
    // Handle client timeout disconnections first
    foreach ($connectedClients as $key => $client) {
        if (time() - $client['lastActivity'] > $timeout) {
            $response = "Your time is up. Exiting...\n";
            socket_sendto($socket, $response, strlen($response), 0, $client['client_ip'], $client['client_port']);
            echo "The client $key has exceeded the timeout, disconnecting...\n";
            $removedClients[$key] = $client;
            unset($connectedClients[$key]);
        }
    }
    // Wait for messages from clients
    $buffer = '';
    $client_ip = '';
    $client_port = 0;

    // Receive messages from clients
    $recvResult = socket_recvfrom($socket, $buffer, 1024, 0, $client_ip, $client_port);
    if ($recvResult === false) {
        // Nëse marrim gabim, printo paralajmërimin dhe vazhdo me iteracionin tjetër
        echo "Warning: socket_recvfrom failed: " . socket_strerror(socket_last_error()) . "\n";
        continue;
    }

    $clientKey = "$client_ip:$client_port";
    $mesazhi = explode("|", $buffer);

    if (isset($connectedClients[$clientKey])) {
        $connectedClients[$clientKey]['lastActivity'] = time(); // Përditëson kohën e fundit të aktivitetit
    }

 // Check if the maximum number of clients has been reached
 if (count($connectedClients) >= $maxClients && !isset($connectedClients[$clientKey])) {
    $response = "Connection refused: server is full.";
    socket_sendto($socket, $response, strlen($response), 0, $client_ip, $client_port);
    echo "The maximum number of clients has been reached; unable to connect.\n";
    continue;
}

// Check if the message is a "connect" request
if (isset($mesazhi[1]) && $mesazhi[1] === "connect") {
    if (!isset($connectedClients[$clientKey])) {
        // Register the client connection
        $connectedClients[$clientKey] = [
            'password' => $mesazhi[0],
            'lastActivity' => time(),
            'isAdmin' => ($mesazhi[0] == $adminPassword),
            'client_ip' => $client_ip,
            'client_port' => $client_port
        ];
        echo "The client $client_ip:$client_port has connected\n";
    }
    continue; // Skip further processing for "connect" message
}


if (!isset($connectedClients[$clientKey])) {
    echo "Received message from unregistered client $client_ip:$client_port\n";
    continue;
}

// Kontrollo që mesazhi të ketë të paktën 2 elemente për të shmangur `Undefined array key`
if (!isset($mesazhi[1])) {
    echo "Received malformed message from $client_ip:$client_port\n";
    continue;
}
 // Add client request to appropriate queue based on priority
 $request = [
    'clientKey' => $clientKey,
    'client_ip' => $client_ip,
    'client_port' => $client_port,
    'command' => $mesazhi[1],
    'file' => $mesazhi[2],
    'content' => $mesazhi[3] ?? ''
];

if ($connectedClients[$clientKey]['isAdmin']) {
    $adminRequests[] = $request;
} else {
    $regularRequests[] = $request;
}

// Log request with timestamp and client IP
$timestamp = date('Y-m-d H:i:s');
$logEntry = "[$timestamp] Request from $client_ip:$client_port - Message: $buffer\n";
file_put_contents($requestLogFile, $logEntry, FILE_APPEND);

echo "Message received from $client_ip:$client_port - $buffer\n";

    // Process admin requests first, then regular requests
    if (!empty($adminRequests)) {
        $currentRequest = array_shift($adminRequests);
    } elseif (!empty($regularRequests)) {
        $currentRequest = array_shift($regularRequests);
    } else {
        continue;
    }



    // Extract request details
    $clientKey = $currentRequest['clientKey'];
    $client_ip = $currentRequest['client_ip'];
    $client_port = $currentRequest['client_port'];
    $command = $currentRequest['command'];
    $file = $currentRequest['file'];
    $content = $currentRequest['content'];

     // Handle the 'exit' command
     if ($command === 'exit') {
        echo "Client $client_ip:$client_port has exited.\n";
        $response = "You have disconnected successfully.";
        socket_sendto($socket, $response, strlen($response), 0, $client_ip, $client_port);
        $removedClients[$clientKey] = $connectedClients[$clientKey];
        unset($connectedClients[$clientKey]);
             continue; // Move to the next iteration without processing further commands
    }


// Handle commands: 'write', 'read', 'execute'
if ($command === 'write') {
    if ($connectedClients[$clientKey]['isAdmin']) {
        if ($file === 'log.txt') {
            $response = "You cannot write to log.txt";
        } else {
            file_put_contents(__DIR__ . '/' . $file, "\n$content", FILE_APPEND);
            $response = "Writing to the file was successful";
        }
    } else {
        $response = "You do not have permission to write";
    }
} elseif ($command === 'read') {
    if ($file === 'log.txt') {
        $response = "You cannot read from log.txt";
    } else {
        $filePath = __DIR__ . '/' . $file;
        $response = file_exists($filePath) ? file_get_contents($filePath) : "File not found";
    }
} elseif ($command === 'execute') {
    if ($connectedClients[$clientKey]['isAdmin']) {
        $escapedCommand = escapeshellcmd($file);
        $output = shell_exec($escapedCommand);
        $response = $output === null ? "The command was executed, but with no result." : "Command result:\n" . $output;
    } else {
        $response = "You do not have permission to execute the command.";
    }
}else{

    $response = "Unknown command.";
}

  // Send response back to the client
  socket_sendto($socket, $response, strlen($response), 0, $client_ip, $client_port);
}
socket_close($socket);
?>