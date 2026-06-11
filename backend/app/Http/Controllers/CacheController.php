<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CacheController extends Controller
{
    public function incrementVersions(Request $request)
    {
        $projectRoot = dirname(base_path()); // Go up one level from backend-php
        $updated = [];
        $errors = [];

        // Get all HTML, CSS, JS files - adjust paths relative to project root
        $files = array_merge(
            glob($projectRoot . '/*.html') ?: [],
            glob($projectRoot . '/pages/*.html') ?: [],
            glob($projectRoot . '/backend/admin-panel/*.html') ?: [],
            glob($projectRoot . '/backend/admin-panel/static/*.css') ?: [],
            glob($projectRoot . '/backend/admin-panel/static/*.js') ?: [],
            glob($projectRoot . '/css/*.css') ?: [],
            glob($projectRoot . '/js/*.js') ?: []
        );

        foreach ($files as $file) {
            if (!is_file($file)) continue;

            $content = file_get_contents($file);
            if ($content === false) {
                $errors[] = "Could not read " . basename($file);
                continue;
            }

            // Match any ?v=NUMBER or v=NUMBER pattern and increment
            $newContent = preg_replace_callback(
                '/(\?v=|v=)(\d+)/',
                function ($matches) {
                    $newVersion = (int)$matches[2] + 1;
                    return $matches[1] . $newVersion;
                },
                $content
            );

            if ($newContent !== $content) {
                if (file_put_contents($file, $newContent)) {
                    $relPath = str_replace($projectRoot . '/', '', $file);
                    $updated[$relPath] = 'updated';
                } else {
                    $errors[] = "Failed to update " . basename($file);
                }
            }
        }

        return response()->json([
            'success' => empty($errors),
            'message' => 'Cache versions incremented successfully',
            'files_updated' => count($updated),
            'updated_files' => array_slice(array_keys($updated), 0, 50),
            'errors' => $errors
        ]);
    }
}
