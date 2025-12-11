#!/usr/bin/env php
<?php

/**
 * Build script for osTicket plugin PHAR packaging
 * Adapted from osTicket-plugins make.php for single plugin in root
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

$command = $argv[1] ?? null;

if ($command === 'build') {
    build();
} else {
    usage();
    exit(1);
}

function usage() {
    echo "Usage: php make.php <command>\n";
    echo "\n";
    echo "Commands:\n";
    echo "  Build PHAR file for plugin\n";
    echo "\n";
}

function build() {
    echo "Building PHAR for plugin\n";

    $phar_name = 'osticket-ai-auto-dept-transfer.phar';
    
    // Remove existing PHAR if it exists
    if (file_exists($phar_name)) {
        unlink($phar_name);
    }
    
    // Create PHAR archive
    $phar = new Phar($phar_name);
    $phar->startBuffering();
    
    // Get base path for file inclusion
    $base_path = __DIR__;
    
    // Files and directories to exclude
    $exclude_patterns = [
        '/^\.git\\b/',
        '/^\.github\\b/',
        '/^\.cursor\\b/',
        '/^vendor\\b/',
        '/^node_modules\\b/',
        '/^\.DS_Store\\b/',
        '/^Thumbs\.db\\b/',
        '/\.log\\b/',
        '/\.zip\\b/',
        '/^composer\.(json|lock|phar)$/',
        '/^make\.php$/',
        '/^\.gitignore$/',
        '/^' . preg_quote($phar_name, '/') . '$/',
    ];
    
    // Recursively add files
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $added_files = 0;
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            continue;
        }
        
        $relative_path = str_replace($base_path . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $relative_path = str_replace('\\', '/', $relative_path);
        
        // Check if file should be excluded
        $excluded = false;
        foreach ($exclude_patterns as $pattern) {
            if (preg_match($pattern, $relative_path)) {
                $excluded = true;
                break;
            }
        }
        
        if ($excluded) {
            continue;
        }
        
        // Add file to PHAR
        $phar->addFile($file->getPathname(), $relative_path);
        $added_files++;
    }
    
    // Set stub
    $stub = "<?php\n";
    $stub .= "Phar::mapPhar('$phar_name');\n";
    $stub .= "__HALT_COMPILER();\n";
    $phar->setStub($stub);
    
    $phar->stopBuffering();
    
    echo "PHAR built successfully: $phar_name\n";
    echo "Added $added_files files\n";
    echo "PHAR size: " . formatBytes(filesize($phar_name)) . "\n";
}

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
