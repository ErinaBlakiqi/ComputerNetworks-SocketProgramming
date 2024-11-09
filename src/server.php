<?php
//Vendosja e variablave për IP adresën dhe portin
$server_ip = "0.0.0.0";
$server_port = 1200;

$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_bind($socket, $server_ip, $server_port);
echo "Serveri UDP është gati në $server_ip:$server_port\n";

?>
