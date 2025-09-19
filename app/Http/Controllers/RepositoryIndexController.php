<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RepositoryIndexController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->get('type');
        $category = $request->get('category');
        $search = $request->get('search');

        $query = DB::table('repository_index');

        if ($type) {
            $query->where('type', $type);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('content_preview', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('type')
                      ->orderBy('category')
                      ->orderBy('name')
                      ->paginate(50);

        $stats = $this->getIndexStats();

        return view('repository.index', compact('items', 'stats', 'type', 'category', 'search'));
    }

    public function show($id)
    {
        $item = DB::table('repository_index')->find($id);
        
        if (!$item) {
            abort(404);
        }

        $metadata = json_decode($item->metadata, true);

        return view('repository.show', compact('item', 'metadata'));
    }

    public function api(Request $request)
    {
        $type = $request->get('type');
        $category = $request->get('category');
        $search = $request->get('search');
        $limit = $request->get('limit', 20);

        $query = DB::table('repository_index');

        if ($type) {
            $query->where('type', $type);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('content_preview', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('type')
                      ->orderBy('category')
                      ->orderBy('name')
                      ->limit($limit)
                      ->get();

        return response()->json([
            'data' => $items,
            'stats' => $this->getIndexStats(),
        ]);
    }

    protected function getIndexStats()
    {
        $typeStats = DB::table('repository_index')
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type');

        $categoryStats = DB::table('repository_index')
            ->select('category', DB::raw('count(*) as count'))
            ->whereNotNull('category')
            ->groupBy('category')
            ->get()
            ->pluck('count', 'category');

        $totalSize = DB::table('repository_index')->sum('size');

        return [
            'total_items' => DB::table('repository_index')->count(),
            'by_type' => $typeStats,
            'by_category' => $categoryStats,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
        ];
    }

    protected function formatBytes($size)
    {
        if (!$size) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        return round($size, 2) . ' ' . $units[$unit];
    }
}