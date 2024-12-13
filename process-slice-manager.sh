#!/bin/bash

# Configuration
PIDFILE="/var/run/process-slice-manager.pid"
LOG_FILE="/var/log/process-slice-manager.log"
CGROUP_CPU="/sys/fs/cgroup/cpu"
CGROUP_MEMORY="/sys/fs/cgroup/memory"
POLL_INTERVAL=2

# Declare associative arrays
declare -A USER_DATA
declare -A PACKAGE_DATA

# Ensure dependencies are available
REQUIRED_CMDS=("jq" "ps")
for cmd in "${REQUIRED_CMDS[@]}"; do
    if ! command -v "$cmd" &>/dev/null; then
        echo "Error: Required command '$cmd' is not installed." >&2
        exit 1
    fi
done

# Logging function with levels
log() {
    local level="$1"
    local message="$2"
    echo "$(date '+%Y-%m-%d %H:%M:%S') - [$level] $message" >> "$LOG_FILE"
}

# Convert memory value to bytes
convert_memory_limit() {
    local limit="$1"
    local value="${limit//[!0-9]/}" # Extract numeric value
    local unit="${limit//[0-9]/}"  # Extract unit
    case "${unit^^}" in
        "K") echo $((value * 1024)) ;;
        "M") echo $((value * 1024 * 1024)) ;;
        "G") echo $((value * 1024 * 1024 * 1024)) ;;
        "T") echo $((value * 1024 * 1024 * 1024 * 1024)) ;;
        "") echo "$value" ;; # If no unit, assume bytes
        *) echo "Invalid unit: $unit" >&2; return 1 ;; # Error handling for invalid units
    esac
}

# Setup cgroup slices
setup_cgroup_slices() {
    log "INFO" "Setting up cgroup slices for packages"
    for user in "${!USER_DATA[@]}"; do
        local data="${USER_DATA[$user]}"
        local package
        package=$(echo "$data" | jq -r '.PACKAGE')
        [[ -n "$package" && -z "${PACKAGE_DATA[$package]}" ]] || continue

        local cpu_quota
        cpu_quota=$(echo "$data" | jq -r '.CPU_QUOTA')
        local cpu_period
        cpu_period=$(echo "$data" | jq -r '.CPU_QUOTA_PERIOD')
        local mem_limit
        mem_limit=$(echo "$data" | jq -r '.MEMORY_LIMIT')
        local swap_limit
        swap_limit=$(echo "$data" | jq -r '.SWAP_LIMIT')

        PACKAGE_DATA["$package"]="$cpu_quota:$cpu_period:$mem_limit:$swap_limit"

        # Create CPU cgroup slice
                cpu_period=${cpu_period//ms/}
                cpu_quota=${cpu_quota//%/}
        if [[ -n "$cpu_quota" && -n "$cpu_period" ]]; then
            local cpu_slice_dir="$CGROUP_CPU/$package"
            mkdir -p "$cpu_slice_dir" || { log "ERROR" "Failed to create CPU slice for $package"; continue; }
            local cfs_period_us=$((cpu_period * 1000))
            local cfs_quota_us=$((cfs_period_us * cpu_quota / 100))
            echo "$cfs_period_us" > "$cpu_slice_dir/cpu.cfs_period_us" || log "ERROR" "Failed to set CPU period for $package"
            echo "$cfs_quota_us" > "$cpu_slice_dir/cpu.cfs_quota_us" || log "ERROR" "Failed to set CPU quota for $package"
        fi

        # Create Memory cgroup slice
        if [[ -n "$mem_limit" || -n "$swap_limit" ]]; then
            local mem_slice_dir="$CGROUP_MEMORY/$package"
            mkdir -p "$mem_slice_dir" || { log "ERROR" "Failed to create Memory slice for $package"; continue; }
            if [[ -n "$mem_limit" ]]; then
                local mem_bytes
                mem_bytes=$(convert_memory_limit "$mem_limit")
                echo "$mem_bytes" > "$mem_slice_dir/memory.limit_in_bytes" || log "ERROR" "Failed to set memory limit for $package"
            fi
            if [[ -n "$swap_limit" ]]; then
                local swap_bytes
                swap_bytes=$(convert_memory_limit "$swap_limit")
                local total_bytes=$((mem_bytes + swap_bytes))
                echo "$total_bytes" > "$mem_slice_dir/memory.memsw.limit_in_bytes" || log "ERROR" "Failed to set swap limit for $package"
            fi
        fi
    done
    log "INFO" "Cgroup slices setup completed"
}

# Load user data
load_user_data() {
    log "INFO" "Loading user data"
    local users_json
    users_json=$(/usr/local/hestia/bin/v-list-users json)
    [[ -n "$users_json" ]] || { log "ERROR" "Failed to fetch user data"; return; }

    USER_DATA=()
    PACKAGE_DATA=()
    while read -r user; do
        [[ "$user" == "admin" ]] && continue
        USER_DATA["$user"]=$(echo "$users_json" | jq -c --arg user "$user" '.[$user]')
        log "INFO" "Loaded data for user: $user"
    done < <(echo "$users_json" | jq -r 'keys[]')

    setup_cgroup_slices
}

# Monitor resources
monitor_resources() {
    log "INFO" "Starting process monitoring"
    declare -A known_processes

    while true; do
        while read -r pid user comm; do
            if [[ "$user" != "root" && -z "${known_processes[$pid]}" ]]; then
                local package
                package=$(echo "${USER_DATA[$user]}" | jq -r '.PACKAGE')
                [[ -n "$package" ]] || continue

                echo "$pid" > "$CGROUP_CPU/$package/tasks" 2>/dev/null || log "ERROR" "Failed to assign PID $pid to CPU cgroup"
                echo "$pid" > "$CGROUP_MEMORY/$package/tasks" 2>/dev/null || log "ERROR" "Failed to assign PID $pid to Memory cgroup"

                known_processes["$pid"]="$user"
                log "INFO" "Assigned PID $pid ($comm) of user $user to package $package"
            fi
        done < <(ps -eo pid=,user=,comm= --no-headers)

        for pid in "${!known_processes[@]}"; do
            if ! kill -0 "$pid" 2>/dev/null; then
                log "INFO" "Process $pid terminated"
                unset known_processes["$pid"]
            fi
        done
        sleep "$POLL_INTERVAL"
    done
}

# Cleanup
cleanup() {
    log "INFO" "Stopping service"
    rm -f "$PIDFILE"
    exit 0
}

# Main
case "$1" in
    start)
        [[ -f "$PIDFILE" ]] && { log "ERROR" "Service already running"; exit 1; }
        echo $$ > "$PIDFILE"
        trap cleanup SIGINT SIGTERM
        trap 'load_user_data' SIGHUP
        load_user_data
        monitor_resources
        ;;
    stop)
        [[ -f "$PIDFILE" ]] && { kill -TERM "$(cat "$PIDFILE")" && log "INFO" "Service stopped"; rm -f "$PIDFILE"; } || log "ERROR" "Service not running"
        ;;
    *)
        echo "Usage: $0 {start|stop}"
        exit 1
        ;;
esac
