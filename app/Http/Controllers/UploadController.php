<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Minimal upload endpoint. Swap for your existing upload mechanism; only the Upload ownership record matters here. */
class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'purpose' => ['required', Rule::in(['profile_photo', 'vehicle_photo', 'document'])],
        ]);

        $user = $request->user();
        $file = $request->file('file');
        $key = $file->store("uploads/{$user->id}", config('onboarding.uploads_disk'));

        Upload::create([
            'user_id' => $user->id,
            'key' => $key,
            'purpose' => $data['purpose'],
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json(['data' => ['file_key' => $key]], 201);
    }
}
