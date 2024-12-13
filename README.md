intsall:
nano /etc/systemd/system/process-slice-manager.service
nano /usr/local/bin/process-slice-manager.sh
chmod +x /usr/local/bin/process-slice-manager.sh

# Reload systemd and enable service
systemctl daemon-reload
systemctl enable process-slice-manager.service
systemctl start process-slice-manager.service
