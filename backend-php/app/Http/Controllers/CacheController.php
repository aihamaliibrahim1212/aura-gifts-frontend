<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CacheController extends Controller
{
    public function incrementVersions(Request $request)
    {
        $files = [
            'js/auth.js' => '/js/auth.js',
            'js/api-cache.js' => '/js/api-cache.js',
            'js/main.js' => '/js/main.js',
            'js/cart.js' => '/js/cart.js',
            'css/style.css' => '/css/style.css',
            'css/auth.css' => '/css/auth.css'
        ];

        $projectRoot = base_path();
        $updated = [];
        $errors = [];

        foreach ($files as $fileName => $filePath) {
            $pattern = preg_quote($filePath) . '\?v=(\d+)';

            $htmlFiles = array_merge(
                glob($projectRoot . '/*.html') ?: [],
                glob($projectRoot . '/pages/*.html') ?: [],
                glob($projectRoot . '/backend/admin-panel/*.html') ?: []
            );

            foreach ($htmlFiles as $htmlFile) {
                $content = file_get_contents($htmlFile);
                $newContent = preg_replace_callback(
                    '/' . $pattern . '/',
                    function($matches) {
                        $newVersion = (int)$matches[1] + 1;
                        return substr($matches[0], 0, -strlen($matches[1])) . $newVersion;
                    },
                    $content
                );

                if ($newContent !== $content) {
                    if (file_put_contents($htmlFile, $newContent)) {
                        if (!isset($updated[$fileName])) {
                            $updated[$fileName] = 0;
                        }
                        $updated[$fileName]++;
                    } else {
                        $errors[] = "Failed to update " . basename($htmlFile);
                    }
                }
            }
        }

        if (empty($errors)) {
            return response()->json([
                'success' => true,
                'message' => 'Cache versions incremented successfully',
                'updated' => $updated
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Some files failed to update',
            'errors' => $errors,
            'updated' => $updated
        ], 500);
    }
}
