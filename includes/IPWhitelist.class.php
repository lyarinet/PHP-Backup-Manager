<?php
/**
 * IP Whitelist Class
 * Manages IP whitelist functionality with CIDR support
 */

class IPWhitelist {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Check if an IP address is allowed
     * @param string $ip IP address to check
     * @return bool True if allowed, false otherwise
     */
    public function isIPAllowed($ip) {
        // If IP whitelist is disabled, allow all
        if (!$this->isEnabled()) {
            return true;
        }
        
        $whitelist = $this->getWhitelist();
        if (empty($whitelist)) {
            return true; // No restrictions if whitelist is empty
        }
        
        foreach ($whitelist as $allowedIP) {
            if ($this->ipMatches($ip, $allowedIP)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP whitelist is enabled
     * @return bool
     */
    public function isEnabled() {
        $enabled = $this->db->getSetting('ip_whitelist_enabled', 'false');
        return $enabled === 'true';
    }
    
    /**
     * Enable or disable IP whitelist
     * @param bool $enabled
     */
    public function setEnabled($enabled) {
        $this->db->setSetting('ip_whitelist_enabled', $enabled ? 'true' : 'false');
        $this->logActivity('IP whitelist ' . ($enabled ? 'enabled' : 'disabled'));
    }
    
    /**
     * Get all whitelisted IPs
     * @return array
     */
    public function getWhitelist() {
        $whitelist = $this->db->getSetting('ip_whitelist', '');
        if (empty($whitelist)) {
            return [];
        }
        
        return array_filter(array_map('trim', explode(',', $whitelist)));
    }
    
    /**
     * Add IP to whitelist
     * @param string $ip IP address or CIDR range
     * @return bool Success status
     */
    public function addIP($ip) {
        if (!$this->validateIP($ip)) {
            return false;
        }
        
        $whitelist = $this->getWhitelist();
        
        // Check if IP already exists
        if (in_array($ip, $whitelist)) {
            return true; // Already exists
        }
        
        $whitelist[] = $ip;
        $this->db->setSetting('ip_whitelist', implode(',', $whitelist));
        $this->logActivity("IP added to whitelist: {$ip}");
        
        return true;
    }
    
    /**
     * Remove IP from whitelist
     * @param string $ip IP address to remove
     * @return bool Success status
     */
    public function removeIP($ip) {
        $whitelist = $this->getWhitelist();
        $key = array_search($ip, $whitelist);
        
        if ($key !== false) {
            unset($whitelist[$key]);
            $whitelist = array_values($whitelist); // Re-index array
            $this->db->setSetting('ip_whitelist', implode(',', $whitelist));
            $this->logActivity("IP removed from whitelist: {$ip}");
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate IP address or CIDR range
     * @param string $ip IP address to validate
     * @return bool Valid status
     */
    public function validateIP($ip) {
        // Check for CIDR notation (e.g., 192.168.1.0/24)
        if (strpos($ip, '/') !== false) {
            list($network, $prefix) = explode('/', $ip, 2);
            
            // Validate network part
            if (!filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return false;
            }
            
            // Validate prefix length
            $prefix = (int)$prefix;
            if ($prefix < 0 || $prefix > 32) {
                return false;
            }
            
            return true;
        }
        
        // Validate single IP address
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }
    
    /**
     * Check if an IP matches a whitelist entry (supports CIDR)
     * @param string $ip IP to check
     * @param string $allowedIP Whitelist entry (IP or CIDR)
     * @return bool Match status
     */
    private function ipMatches($ip, $allowedIP) {
        // Direct IP match
        if ($ip === $allowedIP) {
            return true;
        }
        
        // CIDR range match
        if (strpos($allowedIP, '/') !== false) {
            return $this->ipInCIDR($ip, $allowedIP);
        }
        
        return false;
    }
    
    /**
     * Check if IP is within CIDR range
     * @param string $ip IP address
     * @param string $cidr CIDR range
     * @return bool
     */
    private function ipInCIDR($ip, $cidr) {
        list($network, $prefix) = explode('/', $cidr, 2);
        
        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        $mask = -1 << (32 - (int)$prefix);
        
        return ($ipLong & $mask) === ($networkLong & $mask);
    }
    
    /**
     * Get current client IP address
     * @return string
     */
    public function getCurrentIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1'; // Fallback
    }
    
    /**
     * Log activity to activity_logs table
     * @param string $action Description of action
     */
    private function logActivity($action) {
        try {
            $this->db->insert('activity_logs', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'action' => $action,
                'ip_address' => $this->getCurrentIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Failed to log IP whitelist activity: " . $e->getMessage());
        }
    }
    
    /**
     * Get IP whitelist statistics
     * @return array
     */
    public function getStats() {
        $whitelist = $this->getWhitelist();
        $totalIPs = count($whitelist);
        $cidrRanges = 0;
        
        foreach ($whitelist as $ip) {
            if (strpos($ip, '/') !== false) {
                $cidrRanges++;
            }
        }
        
        return [
            'enabled' => $this->isEnabled(),
            'total_ips' => $totalIPs,
            'single_ips' => $totalIPs - $cidrRanges,
            'cidr_ranges' => $cidrRanges,
            'current_ip' => $this->getCurrentIP()
        ];
    }
}
?>
