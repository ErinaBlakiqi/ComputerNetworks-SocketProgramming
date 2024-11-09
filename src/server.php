<?php
//Vendosja e variablave për IP adresën dhe portin
$server_ip = "0.0.0.0";
$server_port = 1200;
$maxClients=4;
$connectedClients=[];
$requestLogFile="log.txt";

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

    // Kontrollon nëse klienti është i lidhur tashmë ose nëse kemi arritur numrin maksimal të klientëve
    if (!in_array($client_ip, $connectedClients) && count($connectedClients) >= $maxClients) {
        echo "Numri maksimal i klientëve u arrit, refuzohet lidhja nga $client_ip:$client_port\n";
        continue;
    }

    // Shton klientin në listën e klientëve të lidhur nëse nuk është tashmë i regjistruar
    if (!in_array($client_ip, $connectedClients)) {
        $connectedClients[] = $client_ip;
        echo "Klienti $client_ip:$client_port u lidh\n";
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
