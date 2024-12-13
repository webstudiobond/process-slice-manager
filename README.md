# Process Slice Manager

This repository contains a service for managing process slices and monitoring them via systemd. It allows for easy package changes, process management, and error monitoring.

## Installation

To install the service and ensure it runs on startup, follow these steps:

1. Create the systemd service file:
    ```bash
    sudo nano /etc/systemd/system/process-slice-manager.service
    ```

2. Create the script that will be used by the service:
    ```bash
    sudo nano /usr/local/bin/process-slice-manager.sh
    ```

3. Make the script executable:
    ```bash
    sudo chmod +x /usr/local/bin/process-slice-manager.sh
    ```

4. Reload systemd to recognize the new service and enable it to start at boot:
    ```bash
    sudo systemctl daemon-reload
    sudo systemctl enable process-slice-manager.service
    sudo systemctl start process-slice-manager.service
    ```

## Monitoring

To monitor the service logs, open another terminal and run the following command:
```bash
tail -f /var/log/process-monitor.log