<?php

namespace App\Services;

use App\Models\User;
use App\Models\VisualFeedback;
use App\Models\VisualFeedbackAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VisualFeedbackFileService
{
    private const MAX_SCREENSHOT_BYTES = 5242880;

    public function storeScreenshotData(?string $dataUrl, VisualFeedback $feedback): ?string
    {
        if (!$dataUrl) {
            return null;
        }

        if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                'screenshot_data' => 'Screenshot must be a PNG, JPG or WEBP data image.',
            ]);
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $encoded = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode($encoded, true);

        if ($binary === false) {
            throw ValidationException::withMessages([
                'screenshot_data' => 'Screenshot data is invalid.',
            ]);
        }

        if (strlen($binary) > self::MAX_SCREENSHOT_BYTES) {
            throw ValidationException::withMessages([
                'screenshot_data' => 'Screenshot may not be greater than 5 MB.',
            ]);
        }

        $path = 'visual-feedback/' . $feedback->id . '/screenshot-' . now()->format('YmdHis') . '.' . $extension;
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    public function storeAttachment(?UploadedFile $file, VisualFeedback $feedback, User $user): ?VisualFeedbackAttachment
    {
        if (!$file) {
            return null;
        }

        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $safeName = $safeName !== '' ? $safeName : 'attachment';
        $extension = $file->getClientOriginalExtension();
        $fileName = $safeName . '-' . Str::random(8) . ($extension ? '.' . $extension : '');
        $path = $file->storeAs('visual-feedback/' . $feedback->id . '/attachments', $fileName, 'local');

        return $feedback->attachments()->create([
            'user_id' => $user->getKey(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize() ?: 0,
        ]);
    }
}
