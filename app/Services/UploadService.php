<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadService
{
    public function storeAttachment(
        UploadedFile $file,
        string $attachmentType,
        int $attachmentId,
        string $field,
        ?int $sortOrder = null
    ): object {
        $path = $this->buildPath($attachmentType, $attachmentId, $field, $file->getClientOriginalExtension());

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $recordId = DB::table('system_files')->insertGetId([
            'disk_name' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'content_type' => $file->getMimeType(),
            'title' => null,
            'description' => null,
            'field' => $field,
            'attachment_id' => $attachmentId,
            'attachment_type' => $attachmentType,
            'is_public' => 1,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) array_merge(
            ['id' => $recordId],
            (array) $this->getFileRecord($recordId)
        );
    }

    public function getFileRecord(int $id): ?object
    {
        $record = DB::table('system_files')->where('id', '=', $id)->first();
        if (!$record) {
            return null;
        }

        $record->url = Storage::disk('public')->url($record->disk_name);

        return $record;
    }

    public function getAttachmentFiles(string $attachmentType, int $attachmentId, string $field): array
    {
        $records = DB::table('system_files')
            ->where('attachment_type', '=', $attachmentType)
            ->where('attachment_id', '=', $attachmentId)
            ->where('field', '=', $field)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($records as $record) {
            $record->url = Storage::disk('public')->url($record->disk_name);
        }

        return $records->toArray();
    }

    public function updateFileMetadata(int $id, array $payload): ?object
    {
        $allowed = [
            'disk_name',
            'file_name',
            'file_size',
            'content_type',
            'title',
            'description',
            'field',
            'attachment_id',
            'attachment_type',
            'is_public',
            'sort_order',
        ];

        $update = array_intersect_key($payload, array_flip($allowed));
        if (!empty($update)) {
            $update['updated_at'] = now();
            DB::table('system_files')->where('id', '=', $id)->update($update);
        }

        return $this->getFileRecord($id);
    }

    public function deleteFile(int $id): bool
    {
        $record = DB::table('system_files')->where('id', '=', $id)->first();
        if (!$record) {
            return false;
        }

        Storage::disk('public')->delete($record->disk_name);
        DB::table('system_files')->where('id', '=', $id)->delete();

        return true;
    }

    private function buildPath(string $attachmentType, int $attachmentId, string $field, ?string $extension): string
    {
        $safeType = strtolower(str_replace('\\', '-', $attachmentType));
        $name = (string) Str::uuid();
        $ext = $extension ? '.' . $extension : '';

        return 'uploads/' . $safeType . '/' . $attachmentId . '/' . $field . '/' . $name . $ext;
    }
}
