<?php
// Clear OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully\n";
} else {
    echo "OPcache not enabled\n";
}

// Clear realpath cache
clearstatcache(true);
echo "Realpath cache cleared\n";

echo "All caches cleared. Please try your request again.\n";
?>
