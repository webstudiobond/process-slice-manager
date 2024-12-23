# Process Slice Manager

This repository contains a service for managing process slices and monitoring them via systemd. It allows for easy package changes, process management, and error monitoring.

## Installation

To install the service and ensure it runs on startup, follow these steps:

1. Create the systemd [service](https://raw.githubusercontent.com/webstudiobond/process-slice-manager/refs/heads/main/process-slice-manager.service) file:
    ```bash
    sudo nano /etc/systemd/system/process-slice-manager.service
    ```

2. Create the [script](https://raw.githubusercontent.com/webstudiobond/process-slice-manager/refs/heads/main/process-slice-manager.sh) that will be used by the service & make the script executable:
    ```bash
    sudo nano /usr/local/bin/process-slice-manager.sh && \
    sudo chmod +x /usr/local/bin/process-slice-manager.sh
    ```
3. Reload systemd to recognize the new service
   ```bash
   sudo systemctl daemon-reload
   ```
4. Enable service to start it at boot:
    ```bash
    sudo systemctl enable process-slice-manager.service
    ```
5. Start service:
   ```bash
   sudo systemctl start process-slice-manager.service
   ```

## Monitoring

To monitor the service logs, open another terminal and run the following command:
```bash
tail -f /var/log/process-slice-manager.log
 ```

## Package change or update

When you change the hestia package, apply the changes by sending a `HUP` signal to the process manager:
```bash
sudo kill -HUP $(cat /var/run/process-slice-manager.pid)
 ```

## Code Changes
If you have changed the code, reload the systemd daemon and restart the service:
```bash
sudo systemctl daemon-reload
sudo systemctl restart process-slice-manager.service
 ```

## Test

1. Create a user in HestiaCP and create a web domain `domain1.com` with DNS settings. If you're on Windows, change the `hosts` file to point the domains to the Hestia server IP (`192.168.209.58`):

    ```bash
    192.168.209.58 domain1.com
    192.168.209.58 domain2.com
    ```

2. In HestiaCP, use the Folder Manager to navigate to the `public_html` folder of `domain1.com`. Delete `index.html`, replace it with `index.php`, and paste the contents of [cpu_ram_stress.php](https://raw.githubusercontent.com/webstudiobond/process-slice-manager/refs/heads/main/cpu_ram_stress.php) from this repository.

3. You can now run the PHP test by setting the maximum amount of RAM and the percentage of CPU usage.

4. Check `htop` to see if the user is properly limited according to the package settings.
