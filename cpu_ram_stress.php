<?php
ini_set('max_execution_time', 0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

class SystemLoadTest {
    private $isRunning = false;
    private $memoryData = [];
    private $targetMemory;
    private $cpuTarget = 90; // 90% CPU target
    private const TOTAL_MEMORY = 15 * 1024 * 1024 * 1024; // 15GB in bytes

    public function __construct() {
        // Set target to 60% of 15GB
        $this->targetMemory = self::TOTAL_MEMORY * 0.6;
        // Set PHP memory limit slightly higher to prevent crashes
        ini_set('memory_limit', ceil(($this->targetMemory * 1.1) / (1024 * 1024)) . 'M');
    }

    public function start() {
        header('Content-Type: application/json');
        ob_end_flush();
        $this->isRunning = true;
        
        try {
            $this->log("Test started - Target memory: " . $this->formatBytes($this->targetMemory));
            
            // Initial memory allocation
            $this->initialMemoryAllocation();
            
            while ($this->isRunning) {
                $startTime = microtime(true);
                
                // CPU Load - 90% usage through precise timing
                $this->cpuWork($startTime);
                
                // Only adjust memory if needed
                $this->maintainMemory();
                
                // Report status
                $this->reportStatus();
                
                if (connection_aborted()) {
                    throw new Exception("Connection aborted by client");
                }
            }
        } catch (Exception $e) {
            $this->log("Error: " . $e->getMessage());
            echo json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]) . "\n";
        }
    }

    private function initialMemoryAllocation() {
        $chunkSize = 10 * 1024 * 1024; // 10MB chunks
        while (memory_get_usage(true) < $this->targetMemory) {
            $this->memoryData[] = str_repeat('A', $chunkSize);
            
            // Report progress during initial allocation
            $this->reportStatus();
            
            // Small delay to prevent overwhelming the system
            usleep(10000);
        }
    }

    private function maintainMemory() {
        $currentUsage = memory_get_usage(true);
        $tolerance = 0.02 * $this->targetMemory; // 2% tolerance

        if ($currentUsage < ($this->targetMemory - $tolerance)) {
            // Add a small amount if we're below target
            $this->memoryData[] = str_repeat('A', 1024 * 1024); // 1MB
        } elseif ($currentUsage > ($this->targetMemory + $tolerance)) {
            // Remove data if we're above target
            array_pop($this->memoryData);
        }
    }

    private function cpuWork($startTime) {
        // Work for 90ms
        $endWork = $startTime + 0.09;
        while (microtime(true) < $endWork) {
            // More intensive CPU operations for 90% load
            for ($i = 0; $i < 2000; $i++) {
                $x = sin($i) * cos($i) * tan($i) * sqrt($i);
            }
        }
        
        // Sleep for 10ms to maintain 90% CPU
        $sleepUntil = $startTime + 0.1; // 100ms total cycle
        $remainingSleep = ($sleepUntil - microtime(true)) * 1000000;
        if ($remainingSleep > 0) {
            usleep($remainingSleep);
        }
    }

    private function reportStatus() {
        $currentMemory = memory_get_usage(true);
        $status = [
            "status" => "running",
            "memory_usage" => $this->formatBytes($currentMemory),
            "memory_target" => $this->formatBytes($this->targetMemory),
            "memory_percentage" => round(($currentMemory / self::TOTAL_MEMORY) * 100, 2),
            "timestamp" => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($status) . "\n";
        ob_flush();
        flush();
    }

    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }

    private function log($message) {
        echo json_encode([
            "status" => "log",
            "message" => $message,
            "timestamp" => date('Y-m-d H:i:s')
        ]) . "\n";
        ob_flush();
        flush();
    }
}

if (isset($_POST['action'])) {
    $test = new SystemLoadTest();
    if ($_POST['action'] === 'start') {
        $test->start();
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>System Load Test (90% CPU / 60% RAM)</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .button { padding: 10px 20px; margin: 10px; cursor: pointer; border: none; border-radius: 4px; }
        .start { background: #4CAF50; color: white; }
        .stop { background: #f44336; color: white; }
        #status { margin: 20px 0; padding: 10px; border: 1px solid #ddd; }
        #console { 
            margin: 20px 0; 
            padding: 10px; 
            border: 1px solid #333; 
            background: #f8f8f8; 
            height: 300px; 
            overflow-y: scroll; 
            font-family: monospace; 
            white-space: pre-wrap;
        }
        .error { color: #f44336; }
        .info { color: #2196F3; }
    </style>
</head>
<body>
    <h1>System Load Test (90% CPU / 60% RAM)</h1>
    <div>
        <button class="button start" onclick="startTest()">Start Test</button>
        <button class="button stop" onclick="stopTest()">Stop Test</button>
    </div>
    <div id="status">Status: Ready</div>
    <div id="console">Console Output:
</div>

    <script>
        let abortController = null;

        function logToConsole(message, isError = false) {
            const console = document.getElementById('console');
            const timestamp = new Date().toLocaleTimeString();
            const className = isError ? 'error' : 'info';
            console.innerHTML += `<div class="${className}">[${timestamp}] ${message}</div>`;
            console.scrollTop = console.scrollHeight;
        }

        async function startTest() {
            if (abortController) {
                logToConsole('Test is already running');
                return;
            }

            document.getElementById('status').textContent = 'Status: Starting...';
            logToConsole('Starting system load test...');
            
            try {
                abortController = new AbortController();
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=start',
                    signal: abortController.signal
                });

                const reader = response.body.getReader();
                const decoder = new TextDecoder();

                while (true) {
                    const {value, done} = await reader.read();
                    if (done) break;
                    
                    const text = decoder.decode(value);
                    const lines = text.split('\n').filter(line => line.trim());
                    
                    for (const line of lines) {
                        try {
                            const data = JSON.parse(line);
                            if (data.status === 'error') {
                                logToConsole(data.message, true);
                                document.getElementById('status').textContent = `Error: ${data.message}`;
                            } else if (data.status === 'log') {
                                logToConsole(data.message);
                            } else if (data.status === 'running') {
                                document.getElementById('status').textContent = 
                                    `Memory: ${data.memory_usage} / ${data.memory_target} (${data.memory_percentage}%)`;
                                logToConsole(`Memory Usage: ${data.memory_percentage}%`);
                            }
                        } catch (e) {
                            logToConsole(`Parse error: ${line}`, true);
                        }
                    }
                }
            } catch (error) {
                if (error.name === 'AbortError') {
                    logToConsole('Test stopped by user');
                } else {
                    logToConsole(`Error: ${error.message}`, true);
                }
            }
        }

        async function stopTest() {
            if (abortController) {
                abortController.abort();
                abortController = null;
                logToConsole('Stopping test...');
                document.getElementById('status').textContent = 'Status: Stopping...';
            }
        }
    </script>
</body>
</html>
