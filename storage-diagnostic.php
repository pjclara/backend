<?php

/**
 * 🔍 Storage Diagnostic Script
 * 
 * Run this script to diagnose storage configuration issues
 * 
 * Usage:
 *   php storage-diagnostic.php
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Storage;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║            📦 Storage Configuration Diagnostic                 ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// 1. Environment Check
echo "📋 ENVIRONMENT CONFIGURATION\n";
echo str_repeat("─", 64) . "\n";
echo "APP_URL:           " . config('app.url') . "\n";
echo "FILESYSTEM_DISK:   " . config('filesystems.default') . "\n";
echo "Environment:       " . app()->environment() . "\n";
echo "\n";

// 2. Storage Paths
echo "📁 STORAGE PATHS\n";
echo str_repeat("─", 64) . "\n";
echo "Storage Path:      " . storage_path() . "\n";
echo "Public Path:       " . public_path() . "\n";
echo "Public Disk Root:  " . config('filesystems.disks.public.root') . "\n";
echo "Public Disk URL:   " . config('filesystems.disks.public.url') . "\n";
echo "\n";

// 3. Symlink Check
echo "🔗 SYMBOLIC LINK CHECK\n";
echo str_repeat("─", 64) . "\n";
$symlinkPath = public_path('storage');
$symlinkTarget = storage_path('app/public');

if (is_link($symlinkPath)) {
    $linkTarget = readlink($symlinkPath);
    echo "✅ Symlink EXISTS\n";
    echo "   Path:   {$symlinkPath}\n";
    echo "   Points: {$linkTarget}\n";
    
    if (realpath($linkTarget) === false) {
        echo "⚠️  WARNING: Symlink target does not exist!\n";
    } else {
        echo "✅ Symlink target is valid\n";
    }
} else {
    echo "❌ Symlink DOES NOT EXIST\n";
    echo "   Expected: {$symlinkPath} -> {$symlinkTarget}\n";
    echo "   Action: Run 'php artisan storage:link' or create manually\n";
}
echo "\n";

// 4. Audio Directory Check
echo "🎵 AUDIO DIRECTORY CHECK\n";
echo str_repeat("─", 64) . "\n";
$audioPath = 'audio/sentences';
$fullAudioPath = storage_path('app/public/' . $audioPath);

if (is_dir($fullAudioPath)) {
    echo "✅ Audio directory EXISTS: {$audioPath}\n";
    
    // List audio files
    $files = glob($fullAudioPath . '/*.mp3');
    $fileCount = count($files);
    echo "   Files found: {$fileCount} MP3 files\n";
    
    if ($fileCount > 0) {
        echo "\n   📄 Sample files (first 5):\n";
        foreach (array_slice($files, 0, 5) as $file) {
            $filename = basename($file);
            $filesize = filesize($file);
            $filesizeKB = round($filesize / 1024, 2);
            echo "      • {$filename} ({$filesizeKB} KB)\n";
        }
    }
    
    // Check permissions
    $perms = fileperms($fullAudioPath);
    $permsOctal = substr(sprintf('%o', $perms), -4);
    echo "\n   Permissions: {$permsOctal}\n";
    
    if (is_writable($fullAudioPath)) {
        echo "   ✅ Directory is WRITABLE\n";
    } else {
        echo "   ❌ Directory is NOT WRITABLE\n";
        echo "   Action: Run 'chmod -R 775 {$fullAudioPath}'\n";
    }
    
} else {
    echo "❌ Audio directory DOES NOT EXIST: {$audioPath}\n";
    echo "   Action: Create with 'mkdir -p {$fullAudioPath}'\n";
}
echo "\n";

// 5. URL Generation Test
echo "🌐 URL GENERATION TEST\n";
echo str_repeat("─", 64) . "\n";
$testPath = 'audio/sentences/exercise-1-test.mp3';
$generatedUrl = Storage::disk('public')->url($testPath);
echo "Test Path:     {$testPath}\n";
echo "Generated URL: {$generatedUrl}\n";
echo "\n";

$expectedPattern = '/\/storage\/audio\/sentences\//';
if (preg_match($expectedPattern, $generatedUrl)) {
    echo "✅ URL format is CORRECT\n";
    echo "   Pattern matches: /storage/audio/sentences/...\n";
} else {
    echo "⚠️  URL format may be INCORRECT\n";
    echo "   Expected pattern: /storage/audio/sentences/...\n";
    echo "   Action: Check APP_URL in .env and config/filesystems.php\n";
}
echo "\n";

// 6. Route Check
echo "🛣️  ROUTE CONFIGURATION CHECK\n";
echo str_repeat("─", 64) . "\n";

try {
    $routes = app('router')->getRoutes();
    $apiExercisesRoute = null;
    
    foreach ($routes as $route) {
        if ($route->uri() === 'api/exercises/{exercise}' && in_array('GET', $route->methods())) {
            $apiExercisesRoute = $route;
            break;
        }
    }
    
    if ($apiExercisesRoute) {
        echo "✅ API Route: GET /api/exercises/{exercise}\n";
        
        // Check for UUID constraint
        $wheres = $apiExercisesRoute->wheres;
        if (isset($wheres['exercise']) && strpos($wheres['exercise'], 'uuid') !== false) {
            echo "✅ UUID constraint is ACTIVE\n";
            echo "   This prevents audio files from matching this route\n";
        } else {
            echo "⚠️  UUID constraint NOT FOUND\n";
            echo "   Action: Add ->whereUuid('exercise') to routes/api.php\n";
        }
    }
} catch (\Exception $e) {
    echo "⚠️  Could not check routes: " . $e->getMessage() . "\n";
}
echo "\n";

// 7. Test File Access
echo "🧪 TEST FILE ACCESS\n";
echo str_repeat("─", 64) . "\n";

if (is_link($symlinkPath) && is_dir($fullAudioPath)) {
    // Get first MP3 file
    $files = glob($fullAudioPath . '/*.mp3');
    
    if (!empty($files)) {
        $testFile = basename($files[0]);
        $testFilePath = $audioPath . '/' . $testFile;
        $testFileUrl = Storage::disk('public')->url($testFilePath);
        
        echo "Sample file: {$testFile}\n";
        echo "Storage path: {$testFilePath}\n";
        echo "Public URL: {$testFileUrl}\n";
        echo "\n";
        echo "📎 Test this URL in your browser:\n";
        echo "   {$testFileUrl}\n";
        echo "\n";
        echo "Expected result: Audio file should play or download\n";
    } else {
        echo "⚠️  No MP3 files found to test\n";
        echo "   Generate audio files first\n";
    }
} else {
    echo "⚠️  Cannot test - symlink or directory missing\n";
}
echo "\n";

// 8. Summary
echo "═══════════════════════════════════════════════════════════════\n";
echo "📊 DIAGNOSTIC SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n";

$issues = [];

if (!is_link($symlinkPath)) {
    $issues[] = "❌ Symlink missing - run: php artisan storage:link";
}

if (!is_dir($fullAudioPath)) {
    $issues[] = "❌ Audio directory missing - create: mkdir -p {$fullAudioPath}";
}

if (is_dir($fullAudioPath) && !is_writable($fullAudioPath)) {
    $issues[] = "❌ Audio directory not writable - run: chmod -R 775 {$fullAudioPath}";
}

if (!preg_match($expectedPattern, $generatedUrl)) {
    $issues[] = "⚠️  URL generation may be incorrect - check APP_URL in .env";
}

if (empty($issues)) {
    echo "✅ ALL CHECKS PASSED\n";
    echo "\n";
    echo "Your storage configuration appears to be correct!\n";
    echo "Audio files should be accessible via /storage/audio/sentences/\n";
} else {
    echo "⚠️  ISSUES FOUND:\n";
    echo "\n";
    foreach ($issues as $issue) {
        echo "   {$issue}\n";
    }
    echo "\n";
    echo "Please fix these issues and run this script again.\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";

echo "💡 TIPS:\n";
echo "   • Production: Ensure symlink exists in public_html/storage\n";
echo "   • Permissions: Use 775 for directories, 644 for files\n";
echo "   • URL Check: Test URLs in browser after deployment\n";
echo "   • Logs: Check storage/logs/laravel.log for errors\n";
echo "\n";

echo "📖 For more help, see: PRODUCTION_STORAGE_SETUP.md\n";
echo "\n";
