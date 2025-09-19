<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IndexRepository extends Command
{
    protected $signature = 'repo:index {--rebuild : Rebuild the entire index}';
    protected $description = 'Index repository files and structure for better searchability';

    protected $indexData = [];

    public function handle()
    {
        $this->info('🚀 Starting repository indexing...');

        if ($this->option('rebuild')) {
            $this->info('🔄 Rebuilding entire index...');
            $this->clearIndex();
        }

        $this->createIndexTable();
        $this->indexProjectStructure();
        $this->indexSourceFiles();
        $this->indexDatabaseStructure();
        $this->indexRoutes();
        $this->indexConfigurations();
        $this->generateIndexSummary();

        $this->info('✅ Repository indexing completed successfully!');
        $this->displayIndexStats();
    }

    protected function createIndexTable()
    {
        if (!Schema::hasTable('repository_index')) {
            Schema::create('repository_index', function ($table) {
                $table->id();
                $table->string('type'); // file, route, config, database, etc.
                $table->string('category')->nullable();
                $table->string('name');
                $table->string('path')->nullable();
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->text('content_preview')->nullable();
                $table->integer('size')->nullable();
                $table->timestamp('last_modified')->nullable();
                $table->timestamps();
                
                $table->index(['type', 'category']);
                $table->index('name');
            });
        }
    }

    protected function clearIndex()
    {
        if (Schema::hasTable('repository_index')) {
            DB::table('repository_index')->truncate();
        }
    }

    protected function indexProjectStructure()
    {
        $this->info('📁 Indexing project structure...');

        $structure = $this->getDirectoryStructure(base_path());
        
        DB::table('repository_index')->insert([
            'type' => 'structure',
            'category' => 'project',
            'name' => 'Project Structure',
            'path' => '/',
            'description' => 'Complete project directory structure',
            'metadata' => json_encode($structure),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function indexSourceFiles()
    {
        $this->info('📄 Indexing source files...');

        $directories = [
            'app' => 'Application Logic',
            'resources' => 'Resources (Views, Assets)',
            'routes' => 'Route Definitions',
            'config' => 'Configuration Files',
            'database' => 'Database Files',
            'tests' => 'Test Files',
        ];

        foreach ($directories as $dir => $description) {
            $path = base_path($dir);
            if (File::exists($path)) {
                $this->indexDirectory($path, $dir, $description);
            }
        }
    }

    protected function indexDirectory($path, $category, $description)
    {
        $files = File::allFiles($path);

        foreach ($files as $file) {
            $relativePath = str_replace(base_path() . '/', '', $file->getPathname());
            $extension = $file->getExtension();
            $size = $file->getSize();
            $lastModified = date('Y-m-d H:i:s', $file->getMTime());

            $content = '';
            $metadata = [
                'extension' => $extension,
                'size_human' => $this->formatBytes($size),
                'lines' => 0,
            ];

            // Read file content for indexing (limit to reasonable size)
            if ($size < 1024 * 1024) { // 1MB limit
                try {
                    $content = File::get($file->getPathname());
                    $metadata['lines'] = substr_count($content, "\n") + 1;
                    
                    // Create preview (first 500 characters)
                    $preview = strlen($content) > 500 ? substr($content, 0, 500) . '...' : $content;
                } catch (\Exception $e) {
                    $preview = 'Binary file or read error';
                }
            } else {
                $preview = 'Large file - content not indexed';
            }

            // Add specific metadata based on file type
            if ($extension === 'php') {
                $metadata['type'] = 'PHP';
                $metadata['classes'] = $this->extractPHPClasses($content);
                $metadata['functions'] = $this->extractPHPFunctions($content);
            } elseif ($extension === 'js') {
                $metadata['type'] = 'JavaScript';
            } elseif ($extension === 'css') {
                $metadata['type'] = 'CSS';
            } elseif ($extension === 'blade.php') {
                $metadata['type'] = 'Blade Template';
            }

            DB::table('repository_index')->insert([
                'type' => 'file',
                'category' => $category,
                'name' => $file->getFilename(),
                'path' => $relativePath,
                'description' => $this->generateFileDescription($file, $extension),
                'metadata' => json_encode($metadata),
                'content_preview' => $preview,
                'size' => $size,
                'last_modified' => $lastModified,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function indexDatabaseStructure()
    {
        $this->info('🗄️ Indexing database structure...');

        // Index migrations
        $migrationPath = database_path('migrations');
        if (File::exists($migrationPath)) {
            $migrations = File::files($migrationPath);
            
            foreach ($migrations as $migration) {
                $content = File::get($migration->getPathname());
                $tables = $this->extractTableNames($content);
                
                DB::table('repository_index')->insert([
                    'type' => 'database',
                    'category' => 'migration',
                    'name' => $migration->getFilename(),
                    'path' => str_replace(base_path() . '/', '', $migration->getPathname()),
                    'description' => 'Database migration file',
                    'metadata' => json_encode([
                        'tables' => $tables,
                        'operations' => $this->extractMigrationOperations($content),
                    ]),
                    'content_preview' => substr($content, 0, 500),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Index models
        $modelPath = app_path('Models');
        if (File::exists($modelPath)) {
            $models = File::files($modelPath);
            
            foreach ($models as $model) {
                $content = File::get($model->getPathname());
                $className = pathinfo($model->getFilename(), PATHINFO_FILENAME);
                
                DB::table('repository_index')->insert([
                    'type' => 'database',
                    'category' => 'model',
                    'name' => $className,
                    'path' => str_replace(base_path() . '/', '', $model->getPathname()),
                    'description' => "Eloquent model for {$className}",
                    'metadata' => json_encode([
                        'class' => $className,
                        'relationships' => $this->extractModelRelationships($content),
                        'fillable' => $this->extractFillableFields($content),
                    ]),
                    'content_preview' => substr($content, 0, 500),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    protected function indexRoutes()
    {
        $this->info('🛣️ Indexing routes...');

        $routeFiles = ['web.php', 'api.php', 'console.php'];
        
        foreach ($routeFiles as $routeFile) {
            $path = base_path("routes/{$routeFile}");
            if (File::exists($path)) {
                $content = File::get($path);
                $routes = $this->extractRoutes($content);
                
                DB::table('repository_index')->insert([
                    'type' => 'route',
                    'category' => pathinfo($routeFile, PATHINFO_FILENAME),
                    'name' => $routeFile,
                    'path' => "routes/{$routeFile}",
                    'description' => "Route definitions for " . pathinfo($routeFile, PATHINFO_FILENAME),
                    'metadata' => json_encode([
                        'routes' => $routes,
                        'count' => count($routes),
                    ]),
                    'content_preview' => substr($content, 0, 500),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    protected function indexConfigurations()
    {
        $this->info('⚙️ Indexing configurations...');

        $configPath = config_path();
        if (File::exists($configPath)) {
            $configs = File::files($configPath);
            
            foreach ($configs as $config) {
                $content = File::get($config->getPathname());
                $configName = pathinfo($config->getFilename(), PATHINFO_FILENAME);
                
                DB::table('repository_index')->insert([
                    'type' => 'config',
                    'category' => 'application',
                    'name' => $configName,
                    'path' => str_replace(base_path() . '/', '', $config->getPathname()),
                    'description' => "Configuration for {$configName}",
                    'metadata' => json_encode([
                        'keys' => $this->extractConfigKeys($content),
                    ]),
                    'content_preview' => substr($content, 0, 500),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    protected function generateIndexSummary()
    {
        $this->info('📊 Generating index summary...');

        $stats = DB::table('repository_index')
            ->select('type', 'category', DB::raw('count(*) as count'))
            ->groupBy('type', 'category')
            ->get();

        $summary = [
            'total_items' => DB::table('repository_index')->count(),
            'by_type' => $stats->groupBy('type')->map(function ($items) {
                return $items->sum('count');
            }),
            'by_category' => $stats->pluck('count', 'category'),
            'generated_at' => now()->toISOString(),
        ];

        DB::table('repository_index')->insert([
            'type' => 'summary',
            'category' => 'meta',
            'name' => 'Index Summary',
            'description' => 'Complete repository index summary and statistics',
            'metadata' => json_encode($summary),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function displayIndexStats()
    {
        $stats = DB::table('repository_index')
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get();

        $this->info("\n📈 Index Statistics:");
        $this->table(['Type', 'Count'], $stats->map(function ($stat) {
            return [$stat->type, $stat->count];
        })->toArray());

        $totalSize = DB::table('repository_index')->sum('size');
        $this->info("💾 Total indexed size: " . $this->formatBytes($totalSize));
    }

    // Helper methods
    protected function getDirectoryStructure($path, $maxDepth = 3, $currentDepth = 0)
    {
        if ($currentDepth >= $maxDepth) return [];

        $structure = [];
        $items = File::glob($path . '/*');

        foreach ($items as $item) {
            $name = basename($item);
            if (in_array($name, ['.git', 'vendor', 'node_modules', '.env'])) continue;

            if (File::isDirectory($item)) {
                $structure[$name] = [
                    'type' => 'directory',
                    'children' => $this->getDirectoryStructure($item, $maxDepth, $currentDepth + 1)
                ];
            } else {
                $structure[$name] = [
                    'type' => 'file',
                    'size' => File::size($item)
                ];
            }
        }

        return $structure;
    }

    protected function extractPHPClasses($content)
    {
        preg_match_all('/class\s+(\w+)/', $content, $matches);
        return $matches[1] ?? [];
    }

    protected function extractPHPFunctions($content)
    {
        preg_match_all('/function\s+(\w+)/', $content, $matches);
        return $matches[1] ?? [];
    }

    protected function extractTableNames($content)
    {
        preg_match_all('/Schema::create\([\'"](\w+)[\'"]/', $content, $matches);
        return $matches[1] ?? [];
    }

    protected function extractMigrationOperations($content)
    {
        $operations = [];
        if (strpos($content, 'Schema::create') !== false) $operations[] = 'create';
        if (strpos($content, 'Schema::table') !== false) $operations[] = 'modify';
        if (strpos($content, 'Schema::drop') !== false) $operations[] = 'drop';
        return $operations;
    }

    protected function extractModelRelationships($content)
    {
        $relationships = [];
        $patterns = [
            'hasOne' => '/hasOne\([\'"]?(\w+)[\'"]?/',
            'hasMany' => '/hasMany\([\'"]?(\w+)[\'"]?/',
            'belongsTo' => '/belongsTo\([\'"]?(\w+)[\'"]?/',
            'belongsToMany' => '/belongsToMany\([\'"]?(\w+)[\'"]?/',
        ];

        foreach ($patterns as $type => $pattern) {
            preg_match_all($pattern, $content, $matches);
            if (!empty($matches[1])) {
                $relationships[$type] = $matches[1];
            }
        }

        return $relationships;
    }

    protected function extractFillableFields($content)
    {
        preg_match('/\$fillable\s*=\s*\[(.*?)\]/s', $content, $matches);
        if (isset($matches[1])) {
            $fields = explode(',', $matches[1]);
            return array_map(function($field) {
                return trim(str_replace(['\'', '"'], '', $field));
            }, $fields);
        }
        return [];
    }

    protected function extractRoutes($content)
    {
        $routes = [];
        $patterns = [
            '/Route::(get|post|put|patch|delete|any)\([\'"]([^\'"]+)[\'"]/',
            '/Route::resource\([\'"]([^\'"]+)[\'"]/',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $routes[] = [
                    'method' => $match[1] ?? 'resource',
                    'uri' => $match[2] ?? $match[1],
                ];
            }
        }

        return $routes;
    }

    protected function extractConfigKeys($content)
    {
        preg_match_all('/[\'"](\w+)[\'"]\s*=>/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    protected function generateFileDescription($file, $extension)
    {
        $descriptions = [
            'php' => 'PHP source file',
            'js' => 'JavaScript file',
            'css' => 'Stylesheet file',
            'json' => 'JSON configuration file',
            'md' => 'Markdown documentation',
            'blade.php' => 'Blade template file',
            'xml' => 'XML configuration file',
        ];

        return $descriptions[$extension] ?? 'File';
    }

    protected function formatBytes($size)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        return round($size, 2) . ' ' . $units[$unit];
    }
}