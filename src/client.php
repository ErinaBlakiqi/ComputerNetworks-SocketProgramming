<?php
// server details
$server_ip = "localhost";
$server_port = 1200;

// Krijo UDP socket
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

while (true) {
    // Merr mesazh nga perdoruesi
    echo "Enter message to send to server (or 'exit' to quit): ";
    $input = trim(fgets(STDIN));

    // Dergon message ne server
    socket_sendto($socket, $input, strlen($input), 0, $server_ip, $server_port);

    // Qysh do qe e shkrun so case sensitive
    if (strtolower($input) == "exit") {
        break;
    }

    // Merr pergjigjen nga serveri
    socket_recvfrom($socket, $buffer, 2048, 0, $server_ip, $server_port);
    echo "Response from server: $buffer\n";
}

socket_close($socket);
?>
