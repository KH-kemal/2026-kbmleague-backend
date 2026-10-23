<?php

namespace App\Http\Controllers\News;

use App\Http\Controllers\Controller;
use App\Models\News\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => 'nullable|in:pertandingan,kegiatan,pengumuman',
            'search' => 'nullable|string|max:180',
            'per_page' => 'nullable|integer|min:1|max:30',
            'page' => 'nullable|integer|min:1',
        ]);
        $query = News::query();
        if (!empty($data['category'])) $query->where('category', $data['category']);
        if (!empty($data['search'])) {
            $term = trim($data['search']);
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$term.'%')->orWhere('excerpt', 'like', '%'.$term.'%'));
        }
        $news = $query->select(['id', 'title', 'excerpt', 'cover_image', 'category', 'news_date', 'created_at', 'updated_at'])
            ->orderByRaw('COALESCE(news_date, DATE(created_at)) DESC')->orderByDesc('id')->paginate($data['per_page'] ?? 9);
        return response()->json($news)->header('Cache-Control', 'no-store');
    }

    public function show(News $news): JsonResponse
    {
        return response()->json($news)->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        if (isset($data['images'])) $data['cover_image'] = $data['images'][0];
        $data['news_date'] ??= now()->toDateString();
        $news = News::create($data);
        return response()->json($news, 201)->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, News $news): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        if (isset($data['images'])) $data['cover_image'] = $data['images'][0];
        elseif (isset($data['cover_image']) && $data['cover_image'] !== $news->cover_image) $data['images'] = [$data['cover_image']];
        $news->update($data);
        return response()->json($news)->header('Cache-Control', 'private, no-store');
    }

    public function destroy(News $news): JsonResponse
    {
        $news->delete();
        // Keep cover files: the same uploaded file may be used by another article.
        return response()->json(null, 204);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required|' : 'required|';
        return [
            'news_date' => 'sometimes|required|date_format:Y-m-d',
            'title' => $required.'string|max:180',
            'excerpt' => $required.'string|max:500',
            'cover_image' => ($partial ? 'sometimes|required|' : 'required_without:images|').'url:http,https|max:2048',
            'images' => 'sometimes|required|array|list|min:1|max:3',
            'images.*' => 'required|url:http,https|max:2048|distinct',
            'category' => $required.'in:pertandingan,kegiatan,pengumuman',
            // Plain text only on the frontend; never render this as trusted HTML.
            'content' => $required.'string|max:50000',
        ];
    }
}
