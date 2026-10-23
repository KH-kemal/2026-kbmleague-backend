<?php

namespace App\Http\Controllers\Upload;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $maxKilobytes = in_array($request->input('type'), ['players', 'news'], true) ? 3072 : 2048;
        $maxMegabytes = $maxKilobytes / 1024;

        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,webp|max:' . $maxKilobytes,
            'type' => 'required|in:teams,players,news',
        ], [
            'file.required' => 'Pilih foto yang akan diunggah.',
            'file.image' => 'File harus berupa gambar JPG, PNG, atau WebP.',
            'file.mimes' => 'Gunakan foto JPG, PNG, atau WebP.',
            'file.max' => "Ukuran foto maksimal {$maxMegabytes} MB.",
            'file.uploaded' => 'Foto gagal diterima server. Periksa ukuran file dan coba lagi.',
        ]);

        $folder = match ($request->type) { 'teams' => 'logos', 'news' => 'news', default => 'players' };
        $path = $request->file('file')->store($folder, 'public');
        if (!$path) {
            return response()->json(['message' => 'Foto gagal disimpan di server. Silakan coba lagi.'], 503);
        }
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'url' => $url,
            'path' => $path
        ], 201);
    }
}