<?php
//Vendosja e variablave për IP adresën dhe portin
$server_ip = "0.0.0.0";
$server_port = 1200;
$maxClients=4;
$connectedClients=[];
$removedClients = [];
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
            
            echo "Klienti $key ka kaluar kohën e pritjes, po largohet...\n";
            $removedClients[$key] = $client;  
            unset($connectedClients[$key]);  
        }
    }
    
     // Logon kërkesën me timestamp dhe IP-në e klientit për auditim
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] Kërkesë nga $client_ip:$client_port - Mesazh: $buffer\n";
    file_put_contents($requestLogFile, $logEntry, FILE_APPEND);
// buffer admin123|write|teksti.txt|tekst 
   // mesazhi={amin123,write,tekst....}
    // Përpunon mesazhin e klientit
    echo "Mesazh i marrë nga $client_ip:$client_port - $buffer\n";

    $command = $mesazhi[1];
    $file = $mesazhi[2];
    $content = $mesazhi[3] ?? '';

    if ($command === 'write') {
        if ($connectedClients[$clientKey]['isAdmin']) {
            if ($file === 'log.txt') {
                $response = "Nuk mund të shkruani në log.txt";
                socket_sendto($socket, $response, strlen($response), 0, $client_ip, $client_port);
                continue;
            }
            file_put_contents(__DIR__ . '/' . $file, "\n$content", FILE_APPEND);
            $response = "Shkrimi në fajll u bë me sukses";
        } else {
            $response = "Nuk keni privilegje për të shkruar";
        }
    } elseif ($command === 'read') {
        if (file_exists(__DIR__ . '/' . $file)) {
            $response = file_get_contents(__DIR__ . '/' . $file);
        } else {
            $response = "Fajlli nuk u gjet";
        }
    } elseif ($command === 'execute') {
        if ($connectedClients[$clientKey]['isAdmin']) {
            $escapedCommand = escapeshellcmd($file);
            $output = shell_exec($escapedCommand);
            $response = $output ?: "Komanda u ekzekutua, por pa ndonjë rezultat.";
        } else {
            $response = "Nuk keni privilegje për të ekzekutuar komanda";
        }
    } else {
        $response = "Komandë e panjohur";
    }

    // Dërgon përgjigjen te klienti
    socket_sendto($socket, $response, strlen($response), 0, $client_ip, $client_port);
}
      socket_close($socket);

?>
