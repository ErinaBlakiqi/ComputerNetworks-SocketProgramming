<?php
//Vendosja e variablave për IP adresën dhe portin
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
echo "Serveri UDP është në gjendje dëgjimi në $server_ip:$server_port\n";

$adminRequests = [];   // Queue for admin requests
$regularRequests = []; // Queue for regular requests

while (true) {
    // Prit mesazhe nga klientët
    $buffer = '';
    $client_ip = '';
    $client_port = 0;
   
    // Pranon një mesazh nga një klient
    socket_recvfrom($socket, $buffer, 1024, 0, $client_ip, $client_port);

    $clientKey = "$client_ip:$client_port";
    $mesazhi = explode("|", $buffer);

    if (count($connectedClients) >= $maxClients) {
        echo "Numri maksimal i klientëve u arrit, nuk mund të lidheni tani\n";
        continue;
    } else {
        if (!isset($connectedClients[$clientKey])) {
            if (isset($removedClients[$clientKey])) {
                $connectedClients[$clientKey] = $removedClients[$clientKey]; 
                $connectedClients[$clientKey]['lastActivity'] = time();
                unset($removedClients[$clientKey]);
                echo "Klienti $client_ip:$client_port u rikthye dhe u rinovua me të dhënat e mëparshme\n";
            } else {
                $connectedClients[$clientKey] = [
                    'password' => $mesazhi[0],
                    'lastActivity' => time(),
                    'isAdmin' => ($mesazhi[0] == $adminPassword)
                ];
                echo "Klienti $client_ip:$client_port u lidh\n";
            }
        } else {
            $connectedClients[$clientKey]['lastActivity'] = time();
        }
    }

    foreach ($connectedClients as $key => $client) {
        if (time() - $client['lastActivity'] > $timeout) {
            $response = "Tour time is up. Exiting...\n";
            socket_sendto($socket, $response, strlen($response), 0, $client['client_ip'], $client['client_port']);
            echo "Klienti $key ka kaluar kohën e pritjes, po largohet...\n";
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
    
     // Logon kërkesën me timestamp dhe IP-në e klientit për auditim
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] Kërkesë nga $client_ip:$client_port - Mesazh: $buffer\n";
    file_put_contents($requestLogFile, $logEntry, FILE_APPEND);
    // buffer admin123|write|teksti.txt|tekst 
    // mesazhi={amin123,write,tekst....}
    // Përpunon mesazhin e klientit
    echo "Mesazh i marrë nga $client_ip:$client_port - $buffer\n";

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
                $response = "Nuk mund të shkruani në log.txt";
            } else {
                file_put_contents(__DIR__ . '/' . $file, "\n$content", FILE_APPEND);
                $response = "Shkrimi në fajll u bë me sukses";
            }
        } else {
            $response = "Nuk keni privilegje për të shkruar";
        }
    } elseif ($command === 'read') {
        $filePath = __DIR__ . '/' . $file;
        $response = file_exists($filePath) ? file_get_contents($filePath) : "Fajlli nuk u gjet";
    } elseif ($command === 'execute') {
        if ($connectedClients[$clientKey]['isAdmin']) {
            // Execute the specified command
            $escapedCommand = escapeshellcmd($file); // Escape the command for security
            $output = shell_exec($escapedCommand);   // Execute the command
    
            // Check the output of the command execution
            if ($output === null) {
                $response = "Komanda u ekzekutua, por pa ndonjë rezultat.";
            } else {
                $response = "Rezultati i komandës:\n" . $output;
            }
        } else {
            $response = "Nuk keni privilegje për të ekzekutuar komanda";
        }
    }
    

    // Send response back to the client
    socket_sendto($socket, $response, strlen($response), 0, $client_ip, $client_port);

}
    socket_close($socket);

?>
