<?php
$server_ip = '192.168.200.150';
$server_port = 1200;

// Create the client socket
$client_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$client_socket) {
    die("Couldn't create socket: " . socket_strerror(socket_last_error()));
}

echo "Socket created. Connecting to server at $server_ip:$server_port\n";

// Set authentication credentials
$password = readline("Enter password for access level (for admin privileges, enter the admin password): ");

// Send initial connection message to server
$connectMessage = "$password|connect";
socket_sendto($client_socket, $connectMessage, strlen($connectMessage), 0, $server_ip, $server_port);

// Define a loop to interact with the server
while (true) {
    echo "\nAvailable Commands: read, write, execute, exit\n";
    $command = readline("Enter command: ");

    if ($command == "exit") {
        $exitMessage = "$password|exit";
        socket_sendto($client_socket, $exitMessage, strlen($exitMessage), 0, $server_ip, $server_port);
        break; 
    }

     $file = readline("Enter the file name or command: ");
    $content = "";

    if ($command === "write") {
        $content = readline("Enter content to write: ");
    }

    // Create message in format: "password|command|file|content"
    $message = "$password|$command|$file|$content";

    // Send the request to the server
    $bytes_sent = socket_sendto($client_socket, $message, strlen($message), 0, $server_ip, $server_port);
    if ($bytes_sent === false) {
        echo "Error sending message to server: " . socket_strerror(socket_last_error()) . "\n";
        continue;
    }

     // Receive the response from the server
     $response = '';
     $bytes_received = socket_recvfrom($client_socket, $response, 2048, 0, $server_ip, $server_port);
     if ($bytes_received === false) {
         echo "Error receiving response from server: " . socket_strerror(socket_last_error()) . "\n";
         continue;
     }
     echo "Server response: $response\n";
 
     // Exit if the response indicates timeout or disconnection
     if (strpos($response, "Exiting...") !== false) {
         break; // Exit the loop if "Exiting..." is in the response
     }
 }
 // Close the socket
 socket_close($client_socket);
 echo "Disconnected from server.\n";
 ?>
 