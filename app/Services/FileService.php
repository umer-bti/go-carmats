<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File as FacadeFile;

class FileService
{
    public static function storageFilePath($inputFile = null, $tag = null)
    {
        if (empty($inputFile)) {
            return null;
        }

        $folderPath = "uploads/{$tag}";

        if ($inputFile instanceof UploadedFile) {
            $content = file_get_contents($inputFile);
            $extension = $inputFile->getClientOriginalExtension();
        } elseif (is_string($inputFile)) {
            $content = $inputFile;
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
            $extension = explode('/', $mime)[1] ?? 'bin';
        } else {
            return null;
        }

        $name = time() . '_' . Str::uuid() . '.' . $extension;
        $path = "{$folderPath}/{$name}";

        return compact('name', 'path', 'content');
    }

public function createOrUpdate($inputFile = null, $relatedModel = null, $tag = null, $saveToDatabase = false)
    {
        $storedFile = null;

        if ($inputFile && ($fileData = $this->storageFilePath($inputFile, $tag))) {

            Storage::disk('public')->put($fileData['path'], $fileData['content']);

            // Create or update in database
            if ($saveToDatabase) {
                if ($relatedModel && $relatedModel->id) {
                    if ($relatedModelsFile = File::where(['fileable_type' => get_class($relatedModel), 'fileable_id' => $relatedModel->id, 'tag' => $tag])->first()) {
                        $this->delete_storageImage($relatedModelsFile->path);
                        $storedFile = $relatedModelsFile->update(['name' => $fileData['name'], 'path' => $fileData['path']]);
                    } else {
                        $storedFile = File::create(['name' => $fileData['name'], 'path' => $fileData['path'], 'tag' => $tag, 'fileable_id' => $relatedModel->id, 'fileable_type' => get_class($relatedModel)]);
                    }
                } else {
                    $storedFile = File::create(['name' => $fileData['name'], 'path' => $fileData['path'], 'tag' => $tag, 'fileable_id' => null, 'fileable_type' => null]);
                }
            } else {
                $storedFile = $fileData;
            }
        }

        return $storedFile;
    }

    public function delete($relatedModel)
    {
        $relatedModelFiles = File::where(['fileable_type' => get_class($relatedModel), 'fileable_id' => $relatedModel->id])->get();

        foreach ($relatedModelFiles as $inputFile) {
            $this->delete_storageImage($inputFile->path);
            $inputFile->delete();
        }
    }

    public function deleteByTag($relatedModel, string $tag): void
    {
        $files = File::where([
            'fileable_type' => get_class($relatedModel),
            'fileable_id' => $relatedModel->id,
            'tag' => $tag,
        ])->get();

        foreach ($files as $file) {
            $this->delete_storageImage($file->path);
            $file->delete();
        }
    }

    private function delete_storageImage($path)
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    // Delete the selected file from all the db file (delete not all but specific files)
    function deleteSelected($relatedModel, $fileIdsToKeep = [])
    {
        // $relatedModelFiles = File::where('fileable_type', get_class($relatedModel))
        //     ->where('fileable_id', $relatedModel->id)
        //     ->whereNotIn('id', $fileIdsToKeep)
        //     ->get();

        // foreach ($relatedModelFiles as $inputFile) {
        //     $this->delete_storageImage($inputFile->path);
        //     $inputFile->delete();
        // }
    }
}
