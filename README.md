# UDP Socket Programming in PHP

This project was created as part of the **Computer Networks** course.
This project demonstrates a UDP socket server and client system in PHP. The server listens for incoming client requests, processes commands (like read, write, and execute), and responds accordingly. The client connects to the server, sends commands, and receives responses. The server handles up to a specified maximum number of clients and includes timeout functionality to disconnect inactive clients.

## Features

- **Client-Server Communication via UDP**: The server listens on a specified IP and port, while the client can send requests to the server.
- **Access Control**: Clients must authenticate using a password to get specific privileges (admin or regular).
- **Command Support**:
  - **read**: Read the content of a specified file.
  - **write**: Write content to a file (only for admin users).
  - **execute**: Execute a system command (only for admin users).
- **Client Timeout**: Clients are disconnected after a specified inactivity timeout.
- **Logging**: The server logs client requests for monitoring and auditing purposes.

## Contributors
- Erina Blakiqi
- Erza Gashi
- Ermira Gashi
- Euron Osmani
