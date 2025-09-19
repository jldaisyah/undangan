<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SearchRepository extends Command
{
    protected $signature = 'repo:search {query} {--type=} {--category=} {--limit=10}';
    protected $description = 'Search through the indexed repository';

    public function handle()
    {
        $query = $this->argument('query');
        $type = $this->option('type');
        $category = $this->option('category');
        $limit = $this->option('limit');

        $this->info("🔍 Searching for: '{$query}'");

        $searchQuery = DB::table('repository_index')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('content_preview', 'like', "%{$query}%")
                  ->orWhere('path', 'like', "%{$query}%");
            });

        if ($type) {
            $searchQuery->where('type', $type);
        }

        if ($category) {
            $searchQuery->where('category', $category);
        }

        $results = $searchQuery->limit($limit)->get();

        if ($results->isEmpty()) {
            $this->warn('No results found.');
            return;
        }

        $this->info("Found {$results->count()} results:");
        
        foreach ($results as $result) {
            $this->line('');
            $this->info("📄 {$result->name}");
            $this->line("   Type: {$result->type}" . ($result->category ? " ({$result->category})" : ''));
            $this->line("   Path: {$result->path}");
            if ($result->description) {
                $this->line("   Description: {$result->description}");
            }
            if ($result->size) {
                $this->line("   Size: " . $this->formatBytes($result->size));
            }
        }
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