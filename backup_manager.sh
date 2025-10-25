#!/bin/bash

# Backup Manager Script
# Comprehensive backup solution with interactive checkbox interface

# Configuration
DEFAULT_BACKUP_DIR="/opt/backups"
CONFIG_FILE="$HOME/.backup_manager.conf"
DATE=$(date +%Y%m%d_%H%M%S)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging
LOG_FILE="/var/log/backup_manager.log"

# Function to log messages
log() {
    local level=$1
    local message=$2
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "[$timestamp] [$level] $message" | tee -a "$LOG_FILE"
}

# Function to check requirements
check_requirements() {
    local missing_tools=()
    
    # Check for dialog/whiptail
    if ! command -v dialog &> /dev/null && ! command -v whiptail &> /dev/null; then
        missing_tools+=("dialog or whiptail")
    fi
    
    # Check for compression tools
    if ! command -v tar &> /dev/null; then
        missing_tools+=("tar")
    fi
    if ! command -v gzip &> /dev/null; then
        missing_tools+=("gzip")
    fi
    
    if [ ${#missing_tools[@]} -ne 0 ]; then
        log "ERROR" "Missing required tools: ${missing_tools[*]}"
        echo "Please install missing tools:"
        echo "Ubuntu/Debian: sudo apt-get install ${missing_tools[*]}"
        echo "CentOS/RHEL: sudo yum install ${missing_tools[*]}"
        exit 1
    fi
}

# Function to detect available database systems
detect_databases() {
    MYSQL_AVAILABLE=false
    POSTGRES_AVAILABLE=false
    
    if command -v mysql &> /dev/null && command -v mysqldump &> /dev/null; then
        MYSQL_AVAILABLE=true
    fi
    
    if command -v psql &> /dev/null && command -v pg_dump &> /dev/null; then
        POSTGRES_AVAILABLE=true
    fi
}

# Function to load configuration
load_config() {
    if [ -f "$CONFIG_FILE" ]; then
        source "$CONFIG_FILE"
    else
        # Default configuration
        BACKUP_DIR="$DEFAULT_BACKUP_DIR"
        RETENTION_DAYS=7
        FILES_TO_BACKUP=(
            "/etc"
            "/var/www"
            "/home"
            "/opt/apps"
        )
        MYSQL_USER=""
        MYSQL_PASSWORD=""
        MYSQL_HOST="localhost"
        POSTGRES_USER=""
        POSTGRES_PASSWORD=""
        POSTGRES_HOST="localhost"
    fi
}

# Function to save configuration
save_config() {
    cat > "$CONFIG_FILE" << EOF
# Backup Manager Configuration
BACKUP_DIR="$BACKUP_DIR"
RETENTION_DAYS=$RETENTION_DAYS
FILES_TO_BACKUP=(
    "${FILES_TO_BACKUP[@]}"
)
MYSQL_USER="$MYSQL_USER"
MYSQL_PASSWORD="$MYSQL_PASSWORD"
MYSQL_HOST="$MYSQL_HOST"
POSTGRES_USER="$POSTGRES_USER"
POSTGRES_PASSWORD="$POSTGRES_PASSWORD"
POSTGRES_HOST="$POSTGRES_HOST"
EOF
    chmod 600 "$CONFIG_FILE"
}

# Function to show header
show_header() {
    clear
    echo -e "${BLUE}"
    echo "╔══════════════════════════════════════════════╗"
    echo "║           BACKUP MANAGER v2.0               ║"
    echo "║          Comprehensive Backup Tool          ║"
    echo "╚══════════════════════════════════════════════╝"
    echo -e "${NC}"
}

# Function to create backup directory
create_backup_dir() {
    local backup_path="$1"
    if [ ! -d "$backup_path" ]; then
        if mkdir -p "$backup_path" 2>/dev/null; then
            log "INFO" "Created backup directory: $backup_path"
            return 0
        else
            log "ERROR" "Failed to create backup directory: $backup_path"
            return 1
        fi
    fi
    return 0
}

# Function to backup files
backup_files() {
    local backup_path="$1"
    local files_backup_dir="$backup_path/files"
    
    if ! create_backup_dir "$files_backup_dir"; then
        return 1
    fi
    
    log "INFO" "Starting file backup to: $files_backup_dir"
    local success_count=0
    local fail_count=0
    
    for file_path in "${FILES_TO_BACKUP[@]}"; do
        if [ -e "$file_path" ]; then
            local base_name=$(basename "$file_path")
            local backup_name="${base_name}_${DATE}.tar.gz"
            
            echo -e "${YELLOW}Backing up: $file_path${NC}"
            if tar -czf "$files_backup_dir/$backup_name" "$file_path" 2>/dev/null; then
                echo -e "${GREEN}✓ Successfully backed up: $file_path${NC}"
                log "INFO" "Backed up: $file_path -> $backup_name"
                ((success_count++))
            else
                echo -e "${RED}✗ Failed to backup: $file_path${NC}"
                log "ERROR" "Failed to backup: $file_path"
                ((fail_count++))
            fi
        else
            echo -e "${RED}✗ Path does not exist: $file_path${NC}"
            log "WARN" "Path does not exist: $file_path"
            ((fail_count++))
        fi
    done
    
    log "INFO" "File backup completed: $success_count successful, $fail_count failed"
    return $fail_count
}

# Function to backup MySQL databases
backup_mysql() {
    local backup_path="$1"
    local mysql_backup_dir="$backup_path/mysql"
    
    if ! create_backup_dir "$mysql_backup_dir"; then
        return 1
    fi
    
    log "INFO" "Starting MySQL backup"
    
    # Test MySQL connection
    if ! mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SHOW DATABASES;" &>/dev/null; then
        echo -e "${RED}✗ Cannot connect to MySQL server${NC}"
        log "ERROR" "MySQL connection failed"
        return 1
    fi
    
    # Get list of databases (excluding system databases)
    local databases=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SHOW DATABASES;" | grep -Ev "(Database|information_schema|performance_schema|mysql|sys)")
    
    local success_count=0
    local fail_count=0
    
    for db in $databases; do
        local backup_file="$mysql_backup_dir/${db}_${DATE}.sql.gz"
        echo -e "${YELLOW}Backing up MySQL database: $db${NC}"
        
        if mysqldump -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" --single-transaction --routines --triggers "$db" 2>/dev/null | gzip > "$backup_file"; then
            echo -e "${GREEN}✓ Successfully backed up MySQL: $db${NC}"
            log "INFO" "MySQL backup successful: $db"
            ((success_count++))
        else
            echo -e "${RED}✗ Failed to backup MySQL: $db${NC}"
            log "ERROR" "MySQL backup failed: $db"
            ((fail_count++))
        fi
    done
    
    log "INFO" "MySQL backup completed: $success_count successful, $fail_count failed"
    return $fail_count
}

# Function to backup PostgreSQL databases
backup_postgres() {
    local backup_path="$1"
    local postgres_backup_dir="$backup_path/postgresql"
    
    if ! create_backup_dir "$postgres_backup_dir"; then
        return 1
    fi
    
    log "INFO" "Starting PostgreSQL backup"
    
    # Export password for psql
    export PGPASSWORD="$POSTGRES_PASSWORD"
    
    # Test PostgreSQL connection
    if ! psql -h "$POSTGRES_HOST" -U "$POSTGRES_USER" -l &>/dev/null; then
        echo -e "${RED}✗ Cannot connect to PostgreSQL server${NC}"
        log "ERROR" "PostgreSQL connection failed"
        unset PGPASSWORD
        return 1
    fi
    
    # Get list of databases
    local databases=$(psql -h "$POSTGRES_HOST" -U "$POSTGRES_USER" -l -t 2>/dev/null | cut -d'|' -f1 | sed 's/ //g' | grep -v "template" | grep -v "postgres" | grep -v "^$")
    
    local success_count=0
    local fail_count=0
    
    for db in $databases; do
        local backup_file="$postgres_backup_dir/${db}_${DATE}.sql.gz"
        echo -e "${YELLOW}Backing up PostgreSQL database: $db${NC}"
        
        if pg_dump -h "$POSTGRES_HOST" -U "$POSTGRES_USER" "$db" 2>/dev/null | gzip > "$backup_file"; then
            echo -e "${GREEN}✓ Successfully backed up PostgreSQL: $db${NC}"
            log "INFO" "PostgreSQL backup successful: $db"
            ((success_count++))
        else
            echo -e "${RED}✗ Failed to backup PostgreSQL: $db${NC}"
            log "ERROR" "PostgreSQL backup failed: $db"
            ((fail_count++))
        fi
    done
    
    # Unset password
    unset PGPASSWORD
    
    log "INFO" "PostgreSQL backup completed: $success_count successful, $fail_count failed"
    return $fail_count
}

# Function to clean old backups
clean_old_backups() {
    local backup_path="$1"
    echo -e "${YELLOW}Cleaning backups older than $RETENTION_DAYS days...${NC}"
    
    local deleted_count=$(find "$backup_path" -name "*.tar.gz" -type f -mtime +$RETENTION_DAYS -delete -print | wc -l)
    deleted_count=$((deleted_count + $(find "$backup_path" -name "*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete -print | wc -l)))
    
    echo -e "${GREEN}✓ Cleanup completed. Removed $deleted_count old backup files${NC}"
    log "INFO" "Cleaned up $deleted_count old backup files"
}

# Function to show backup summary
show_summary() {
    local backup_path="$1"
    echo ""
    echo -e "${BLUE}╔══════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║               BACKUP SUMMARY                ║${NC}"
    echo -e "${BLUE}╚══════════════════════════════════════════════╝${NC}"
    echo -e "Backup location: ${GREEN}$backup_path${NC}"
    echo -e "Total size: ${YELLOW}$(du -sh "$backup_path" 2>/dev/null | cut -f1 || echo "Unknown")${NC}"
    echo -e "Backup contents:"
    
    local total_files=0
    if [ -d "$backup_path/files" ]; then
        local file_count=$(find "$backup_path/files" -name "*.tar.gz" -type f | wc -l)
        echo -e "  ${BLUE}• Files:${NC} $file_count backups"
        total_files=$((total_files + file_count))
        
        find "$backup_path/files" -name "*.tar.gz" -type f -exec ls -lh {} \; | \
        while read -r line; do
            echo "    - $(echo "$line" | awk '{print $9 " (" $5 ")"}')"
        done
    fi
    
    if [ -d "$backup_path/mysql" ]; then
        local mysql_count=$(find "$backup_path/mysql" -name "*.sql.gz" -type f | wc -l)
        echo -e "  ${BLUE}• MySQL:${NC} $mysql_count databases"
        total_files=$((total_files + mysql_count))
    fi
    
    if [ -d "$backup_path/postgresql" ]; then
        local postgres_count=$(find "$backup_path/postgresql" -name "*.sql.gz" -type f | wc -l)
        echo -e "  ${BLUE}• PostgreSQL:${NC} $postgres_count databases"
        total_files=$((total_files + postgres_count))
    fi
    
    echo -e "Total backup files: ${GREEN}$total_files${NC}"
    echo -e "Log file: ${YELLOW}$LOG_FILE${NC}"
}

# Function to get user input with fallback
get_input() {
    local prompt="$1"
    local default="$2"
    local value
    
    read -p "$prompt [$default]: " value
    echo "${value:-$default}"
}

# Configuration wizard
configuration_wizard() {
    show_header
    echo -e "${YELLOW}Configuration Wizard${NC}"
    echo ""
    
    BACKUP_DIR=$(get_input "Enter backup directory" "$BACKUP_DIR")
    RETENTION_DAYS=$(get_input "Enter retention days" "$RETENTION_DAYS")
    
    echo ""
    echo -e "${YELLOW}File Backup Paths${NC}"
    echo "Current paths: ${FILES_TO_BACKUP[*]}"
    echo "Enter new paths (one per line, empty line to finish):"
    
    local new_paths=()
    while true; do
        read -r path
        [ -z "$path" ] && break
        new_paths+=("$path")
    done
    
    if [ ${#new_paths[@]} -gt 0 ]; then
        FILES_TO_BACKUP=("${new_paths[@]}")
    fi
    
    echo ""
    echo -e "${YELLOW}MySQL Configuration${NC}"
    if [ "$MYSQL_AVAILABLE" = true ]; then
        MYSQL_USER=$(get_input "MySQL username" "$MYSQL_USER")
        MYSQL_PASSWORD=$(get_input "MySQL password" "$MYSQL_PASSWORD")
        MYSQL_HOST=$(get_input "MySQL host" "$MYSQL_HOST")
    else
        echo -e "${RED}MySQL client not available${NC}"
    fi
    
    echo ""
    echo -e "${YELLOW}PostgreSQL Configuration${NC}"
    if [ "$POSTGRES_AVAILABLE" = true ]; then
        POSTGRES_USER=$(get_input "PostgreSQL username" "$POSTGRES_USER")
        POSTGRES_PASSWORD=$(get_input "PostgreSQL password" "$POSTGRES_PASSWORD")
        POSTGRES_HOST=$(get_input "PostgreSQL host" "$POSTGRES_HOST")
    else
        echo -e "${RED}PostgreSQL client not available${NC}"
    fi
    
    save_config
    echo -e "${GREEN}✓ Configuration saved to $CONFIG_FILE${NC}"
    sleep 2
}

# Interactive backup selection
interactive_backup_selection() {
    while true; do
        show_header
        
        # Determine which UI tool to use
        if command -v dialog &> /dev/null; then
            local dialog_cmd="dialog"
        elif command -v whiptail &> /dev/null; then
            local dialog_cmd="whiptail"
        else
            echo -e "${RED}Error: No dialog tool available${NC}"
            exit 1
        fi
        
        # Create backup options
        local options=()
        options+=("1" "Backup Files" "on")
        
        if [ "$MYSQL_AVAILABLE" = true ] && [ -n "$MYSQL_USER" ]; then
            options+=("2" "Backup MySQL Databases" "off")
        else
            options+=("2" "Backup MySQL Databases (Not configured)" "off")
        fi
        
        if [ "$POSTGRES_AVAILABLE" = true ] && [ -n "$POSTGRES_USER" ]; then
            options+=("3" "Backup PostgreSQL Databases" "off")
        else
            options+=("3" "Backup PostgreSQL Databases (Not configured)" "off")
        fi
        
        options+=("4" "Change Backup Location" "off")
        options+=("5" "Configuration Wizard" "off")
        options+=("6" "Exit" "off")
        
        # Show menu
        if [ "$dialog_cmd" = "dialog" ]; then
            choice=$($dialog_cmd --backtitle "Backup Manager" \
                --title "Select Backup Options" \
                --checklist "Choose what to backup:" 18 60 8 \
                "${options[@]}" 3>&1 1>&2 2>&3)
        else
            choice=$($dialog_cmd --title "Backup Manager" \
                --checklist "Choose what to backup:" 18 60 8 \
                "${options[@]}" 3>&1 1>&2 2>&3)
        fi
        
        # Exit if cancelled
        [ $? -ne 0 ] && exit 0
        
        # Process choices
        local backup_files=false
        local backup_mysql=false
        local backup_postgres=false
        
        for item in $choice; do
            case $item in
                \"1\")
                    backup_files=true
                    ;;
                \"2\")
                    if [ "$MYSQL_AVAILABLE" = true ] && [ -n "$MYSQL_USER" ]; then
                        backup_mysql=true
                    else
                        show_message "Error" "MySQL backup not available. Please configure MySQL in Configuration Wizard."
                    fi
                    ;;
                \"3\")
                    if [ "$POSTGRES_AVAILABLE" = true ] && [ -n "$POSTGRES_USER" ]; then
                        backup_postgres=true
                    else
                        show_message "Error" "PostgreSQL backup not available. Please configure PostgreSQL in Configuration Wizard."
                    fi
                    ;;
                \"4\")
                    if [ "$dialog_cmd" = "dialog" ]; then
                        BACKUP_DIR=$($dialog_cmd --inputbox "Enter new backup directory:" 8 60 "$BACKUP_DIR" 3>&1 1>&2 2>&3)
                    else
                        BACKUP_DIR=$($dialog_cmd --inputbox "Enter new backup directory:" 8 60 "$BACKUP_DIR" 3>&1 1>&2 2>&3)
                    fi
                    save_config
                    ;;
                \"5\")
                    configuration_wizard
                    ;;
                \"6\")
                    echo -e "${GREEN}Goodbye!${NC}"
                    exit 0
                    ;;
            esac
        done
        
        # Execute backup if any option selected
        if $backup_files || $backup_mysql || $backup_postgres; then
            execute_backup "$backup_files" "$backup_mysql" "$backup_postgres"
        fi
    done
}

# Show message dialog
show_message() {
    local title="$1"
    local message="$2"
    
    if command -v dialog &> /dev/null; then
        dialog --msgbox "$message" 10 50
    elif command -v whiptail &> /dev/null; then
        whiptail --title "$title" --msgbox "$message" 10 50
    else
        echo -e "${BLUE}$title:${NC} $message"
        read -p "Press Enter to continue..."
    fi
}

# Execute backup process
execute_backup() {
    local backup_files=$1
    local backup_mysql=$2
    local backup_postgres=$3
    
    local final_backup_dir="$BACKUP_DIR/$DATE"
    
    show_header
    echo -e "${GREEN}Starting backup process...${NC}"
    echo -e "Backup destination: ${YELLOW}$final_backup_dir${NC}"
    echo -e "Start time: ${YELLOW}$(date)${NC}"
    echo ""
    
    # Create backup directory
    if ! create_backup_dir "$final_backup_dir"; then
        show_message "Error" "Failed to create backup directory: $final_backup_dir"
        return 1
    fi
    
    local overall_success=true
    
    # Perform selected backups
    if $backup_files; then
        echo -e "${BLUE}=== FILE BACKUP ===${NC}"
        if ! backup_files "$final_backup_dir"; then
            overall_success=false
        fi
        echo ""
    fi
    
    if $backup_mysql; then
        echo -e "${BLUE}=== MYSQL BACKUP ===${NC}"
        if ! backup_mysql "$final_backup_dir"; then
            overall_success=false
        fi
        echo ""
    fi
    
    if $backup_postgres; then
        echo -e "${BLUE}=== POSTGRESQL BACKUP ===${NC}"
        if ! backup_postgres "$final_backup_dir"; then
            overall_success=false
        fi
        echo ""
    fi
    
    # Clean old backups
    echo -e "${BLUE}=== CLEANUP ===${NC}"
    clean_old_backups "$BACKUP_DIR"
    echo ""
    
    # Show summary
    show_summary "$final_backup_dir"
    echo ""
    echo -e "End time: ${YELLOW}$(date)${NC}"
    
    if $overall_success; then
        echo -e "${GREEN}✓ Backup completed successfully!${NC}"
        log "INFO" "Backup completed successfully: $final_backup_dir"
    else
        echo -e "${YELLOW}⚠ Backup completed with some errors${NC}"
        log "WARN" "Backup completed with errors: $final_backup_dir"
    fi
    
    echo ""
    read -p "Press Enter to continue..."
}

# Command line usage
cmdline_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --files          Backup files"
    echo "  --mysql          Backup MySQL databases"
    echo "  --postgres       Backup PostgreSQL databases"
    echo "  --all            Backup everything"
    echo "  --dir <path>     Custom backup directory"
    echo "  --config         Run configuration wizard"
    echo "  --help           Show this help"
    echo ""
    echo "Interactive mode (no options): Shows checkbox interface"
    exit 1
}

# Command line mode
cmdline_mode() {
    local backup_files=false
    local backup_mysql=false
    local backup_postgres=false
    local custom_backup_dir="$BACKUP_DIR"
    
    while [[ $# -gt 0 ]]; do
        case $1 in
            --files)
                backup_files=true
                shift
                ;;
            --mysql)
                backup_mysql=true
                shift
                ;;
            --postgres)
                backup_postgres=true
                shift
                ;;
            --all)
                backup_files=true
                backup_mysql=true
                backup_postgres=true
                shift
                ;;
            --dir)
                custom_backup_dir="$2"
                shift 2
                ;;
            --config)
                configuration_wizard
                exit 0
                ;;
            --help)
                cmdline_usage
                ;;
            *)
                echo "Unknown option: $1"
                cmdline_usage
                ;;
        esac
    done
    
    if ! $backup_files && ! $backup_mysql && ! $backup_postgres; then
        echo "Error: No backup options selected"
        cmdline_usage
    fi
    
    execute_backup "$backup_files" "$backup_mysql" "$backup_postgres"
}

# Main function
main() {
    # Check requirements
    check_requirements
    
    # Detect available databases
    detect_databases
    
    # Load configuration
    load_config
    
    # Create log directory if it doesn't exist
    local log_dir=$(dirname "$LOG_FILE")
    if [ ! -d "$log_dir" ]; then
        mkdir -p "$log_dir"
    fi
    
    # First run configuration check
    if [ ! -f "$CONFIG_FILE" ] || [ -z "$MYSQL_USER" ] || [ -z "$POSTGRES_USER" ]; then
        echo -e "${YELLOW}First run detected. Starting configuration wizard...${NC}"
        configuration_wizard
    fi
    
    # Run in appropriate mode
    if [ $# -eq 0 ]; then
        # Interactive mode
        interactive_backup_selection
    else
        # Command line mode
        cmdline_mode "$@"
    fi
}

# Run main function
main "$@"