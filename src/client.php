<?php
// Server IP and port 
$server_ip = '';
$server_port = 5000; 

// Create a UDP socket
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    die("Unable to create socket\n");
}



socket_close($socket);
?>
