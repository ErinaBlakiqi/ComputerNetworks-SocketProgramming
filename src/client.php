<?php
$server_ip = '0.0.0.0'; 
$server_port = 1200;      

// Create the client socket
$client_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$client_socket) {
    die("Couldn't create socket: " . socket_strerror(socket_last_error()));
}

echo "Socket created. Connecting to server at $server_ip:$server_port\n";

// Set authentication credentials
$password = readline("Enter password for access level (for admin privileges, enter the admin password): ");

// Define a loop to interact with the server
while (true) {
    echo "\nAvailable Commands: read, write, execute, exit\n";
    $command = readline("Enter command: ");

    if ($command == "exit") {
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
    socket_sendto($client_socket, $message, strlen($message), 0, $server_ip, $server_port);

    // Receive the response from the server
    $response = '';
    socket_recvfrom($client_socket, $response, 1024, 0, $server_ip, $server_port);
    echo "Server response: $response\n";
}

// Close the socket
socket_close($client_socket);
echo "Disconnected from server.\n";
?>
