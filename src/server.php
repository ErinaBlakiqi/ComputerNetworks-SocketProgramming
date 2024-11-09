<?php
//Vendosja e variablave për IP adresën dhe portin
$server_ip = "0.0.0.0";
$server_port = 1200;
$maxClients=4;
$connectedClients=[];
$requestLogFile="log.txt";
$timeout = 50;
$adminPassword="123";

$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

if (!$socket) {
    die("Couldn't create socket: " . socket_strerror(socket_last_error()));
}

if (!socket_bind($socket, $server_ip, $server_port)) {
    die("Couldn't bind socket: " . socket_strerror(socket_last_error()));
}
echo "Serveri UDP është në gjendje dëgjimi në $server_ip:$server_port\n";

while (true) {
    // Prit mesazhe nga klientët
    $buffer = '';
    $client_ip = '';
    $client_port = 0;
   
    // Pranon një mesazh nga një klient
    socket_recvfrom($socket, $buffer, 1024, 0, $client_ip, $client_port);

     $clientKey = "$client_ip:$client_port";
    $mesazhi = explode("; ", $buffer);

     if (count($connectedClients) >= $maxClients) {
        echo "Numri maksimal i klientëve u arrit, nuk mund të lidheni tani\n";
        continue;
    } else {
        if (!isset($connectedClients[$clientKey])) {
            if (isset($removedClients[$clientKey])) {
                $connectedClients[$clientKey] = $removedClients[$clientKey]; 
                unset($removedClients[$clientKey]);
                echo "Klienti $client_ip:$client_port u rikthye dhe u rinovua me të dhënat e mëparshme\n";
            } else {
                $connectedClients[$clientKey] = [
                    'username' => $mesazhi[0],
                    'password' => $mesazhi[1],
                    'lastActivity' => time(),
                    'isAdmin' => ($mesazhi[1] == $adminPassword)
                ];
                echo "Klienti $client_ip:$client_port u lidh\n";
            }
        } else {
            $connectedClients[$clientKey]['lastActivity'] = time();
        }
    }

    foreach ($connectedClients as $key => $client) {
        if (time() - $client['lastActivity'] > $timeout) {
            
            echo "Klienti $key ka kaluar kohën e pritjes, po largohet...\n";
            $removedClients[$key] = $client;  
            unset($connectedClients[$key]);  
        }
    }
    

     // Logon kërkesën me timestamp dhe IP-në e klientit për auditim
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] Kërkesë nga $client_ip:$client_port - Mesazh: $buffer\n";
    file_put_contents($requestLogFile, $logEntry, FILE_APPEND);

    // Përpunon mesazhin e klientit
    echo "Mesazh i marrë nga $client_ip:$client_port - $buffer\n";
    
    // Përgjigje klientit për të konfirmuar që kërkesa është marrë
    $response = "Kërkesa u pranua nga serveri";
    socket_sendto($socket, $response, strlen($response), 0, $client_ip, $client_port);
}
      socket_close($socket);

?>
