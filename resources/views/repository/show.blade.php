<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $item->name }} - Repository Index</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <nav class="flex items-center space-x-2 text-sm text-gray-500 mb-4">
                <a href="{{ route('repository.index') }}" class="hover:text-blue-600">Repository Index</a>
                <span>/</span>
                <span class="text-gray-900">{{ $item->name }}</span>
            </nav>
            
            <div class="flex items-center space-x-4 mb-4">
                <h1 class="text-3xl font-bold text-gray-900">{{ $item->name }}</h1>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                    {{ $item->type }}
                </span>
                @if($item->category)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                        {{ $item->category }}
                    </span>
                @endif
            </div>
            
            @if($item->description)
                <p class="text-gray-600">{{ $item->description }}</p>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- File Info -->
                @if($item->path)
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">File Information</h2>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Path</label>
                                <code class="block bg-gray-100 p-2 rounded text-sm">{{ $item->path }}</code>
                            </div>
                            @if($item->size)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Size</label>
                                    <p class="text-sm text-gray-900">{{ number_format($item->size) }} bytes ({{ number_format($item->size / 1024, 1) }} KB)</p>
                                </div>
                            @endif
                            @if($item->last_modified)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Last Modified</label>
                                    <p class="text-sm text-gray-900">{{ \Carbon\Carbon::parse($item->last_modified)->format('Y-m-d H:i:s') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Content Preview -->
                @if($item->content_preview)
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Content Preview</h2>
                        <div class="bg-gray-50 p-4 rounded-lg overflow-x-auto">
                            <pre class="text-sm text-gray-700 whitespace-pre-wrap">{{ $item->content_preview }}</pre>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Metadata -->
                @if($metadata)
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Metadata</h2>
                        <div class="space-y-3">
                            @foreach($metadata as $key => $value)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 capitalize">{{ str_replace('_', ' ', $key) }}</label>
                                    @if(is_array($value))
                                        @if(empty($value))
                                            <p class="text-sm text-gray-500">None</p>
                                        @else
                                            <ul class="text-sm text-gray-900 list-disc list-inside">
                                                @foreach($value as $item)
                                                    @if(is_array($item))
                                                        <li>{{ json_encode($item) }}</li>
                                                    @else
                                                        <li>{{ $item }}</li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        @endif
                                    @else
                                        <p class="text-sm text-gray-900">{{ $value }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Actions</h2>
                    <div class="space-y-2">
                        <a href="{{ route('repository.index') }}" 
                           class="block w-full text-center bg-gray-100 text-gray-700 px-4 py-2 rounded hover:bg-gray-200 transition-colors">
                            Back to Index
                        </a>
                        @if($item->path && file_exists(base_path($item->path)))
                            <button onclick="copyToClipboard('{{ $item->path }}')" 
                                    class="block w-full text-center bg-blue-100 text-blue-700 px-4 py-2 rounded hover:bg-blue-200 transition-colors">
                                Copy Path
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Path copied to clipboard!');
            });
        }
    </script>
</body>
</html>