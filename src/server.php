<?php
//Setting variables for the IP address and port
$server_ip = '127.0.0.1';
$server_port = 1200;
$maxClients = 4;
$connectedClients = [];
$removedClients = [];
$requestLogFile = "log.txt";
$timeout = 180;
$adminPassword = "123";

$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

if (!$socket) {
    die("Couldn't create socket: " . socket_strerror(socket_last_error()));
}

if (!socket_bind($socket, $server_ip, $server_port)) {
    die("Couldn't bind socket: " . socket_strerror(socket_last_error()));
}
echo "The UDP server is listening on $server_ip:$server_port\n";

$adminRequests = [];   // Queue for admin requests
$regularRequests = []; // Queue for regular requests

while (true) {
    // Wait for the messages from clients
    $buffer = '';
    $client_ip = '';
    $client_port = 0;
   
    // Receive messages from clients
    socket_recvfrom($socket, $buffer, 1024, 0, $client_ip, $client_port);

    $clientKey = "$client_ip:$client_port";
    $mesazhi = explode("|", $buffer);

    if (count($connectedClients) >= $maxClients) {
        echo "The maximum number of clients has been reached; unable to connect.\n";
        continue;
    } else {
        if (!isset($connectedClients[$clientKey])) {
            if (isset($removedClients[$clientKey])) {
                $connectedClients[$clientKey] = $removedClients[$clientKey]; 
                $connectedClients[$clientKey]['lastActivity'] = time();
                unset($removedClients[$clientKey]);
                echo "The client $client_ip:$client_port reconnected and was restored with previous data\n";
            } else {
                $connectedClients[$clientKey] = [
                    'password' => $mesazhi[0],
                    'lastActivity' => time(),
                    'isAdmin' => ($mesazhi[0] == $adminPassword)
                ];
                echo "The client $client_ip:$client_port has connected\n";
            }
        } else {
            $connectedClients[$clientKey]['lastActivity'] = time();
        }
    }

    foreach ($connectedClients as $key => $client) {
        if (time() - $client['lastActivity'] > $timeout) {
            $response = "Tour time is up. Exiting...\n";
            socket_sendto($socket, $response, strlen($response), 0, $client['client_ip'], $client['client_port']);
            echo "The client $key has exceeded the timeout, disconnecting...\n";
            $removedClients[$key] = $client;  
            unset($connectedClients[$key]);  
        }
    }

    // Add client request to appropriate queue based on priority
    $request = [
        'clientKey' => $clientKey,
        'client_ip' => $client_ip,
        'client_port' => $client_port,
        'command' => $mesazhi[1],
        'file' => $mesazhi[2] ?? '',
        'content' => $mesazhi[3] ?? ''
    ];

    if ($connectedClients[$clientKey]['isAdmin']) {
        $adminRequests[] = $request;
    } else {
        $regularRequests[] = $request;
    }
    
     // Logs the request with a timestamp and the client's IP for auditing.
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] Request from $client_ip:$client_port - Message: $buffer\n";
    file_put_contents($requestLogFile, $logEntry, FILE_APPEND);
    // buffer admin123|write|teksti.txt|tekst 
    // message={amin123,write,tekst....}
    // Processes the client's message.
    echo "Message received from $client_ip:$client_port - $buffer\n";

     // Process admin requests first
     if (!empty($adminRequests)) {
        $currentRequest = array_shift($adminRequests); // Dequeue the next admin request
    } elseif (!empty($regularRequests)) {
        $currentRequest = array_shift($regularRequests); // Dequeue the next regular request
    } else {
        continue; // No requests to process
    }

    // Process the current request
    $clientKey = $currentRequest['clientKey'];
    $client_ip = $currentRequest['client_ip'];
    $client_port = $currentRequest['client_port'];
    $command = $currentRequest['command'];
    $file = $currentRequest['file'];
    $content = $currentRequest['content'];


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
        // Check if the file requested is 'log.txt'
        if ($file === 'log.txt') {
            $response = "You cannot read from log.txt";
        } else {
            $filePath = __DIR__ . '/' . $file;
            // Check if the file exists, otherwise return an error message
            $response = file_exists($filePath) ? file_get_contents($filePath) : "File not found";
        }
    } elseif ($command === 'execute') {
        if ($connectedClients[$clientKey]['isAdmin']) {
            // Execute the specified command
            $escapedCommand = escapeshellcmd($file); // Escape the command for security
            $output = shell_exec($escapedCommand);   // Execute the command
    
            // Check the output of the command execution
            if ($output === null) {
                $response = "The command was executed, but with no result.";
            } else {
                $response = "Command result:\n" . $output;
            }
        } else {
            $response = "You do not have permission to execute the command.";
        }
    }
    

    // Send response back to the client
    socket_sendto($socket, $response, strlen($response), 0, $client_ip, $client_port);

}
    socket_close($socket);

?>
