<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class AssignmentDownloadController extends Controller
{
    public function file(Assignment $assignment, AssignmentFile $file): Response
    {
        abort_unless($file->assignment_id === $assignment->id, 404);

        if ($file->kind === AssignmentFile::KIND_ATTACHMENT) {
            abort_unless($file->disk_path && Storage::exists($file->disk_path), 404);

            return Storage::download($file->disk_path, $file->path);
        }

        return response()->streamDownload(
            fn () => print ($file->content),
            basename($file->path),
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    public function zip(Assignment $assignment): BinaryFileResponse
    {
        $files = $assignment->draftFiles;
        abort_if($files->isEmpty(), 404);

        $path = tempnam(sys_get_temp_dir(), 'draft');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $file) {
            $zip->addFromString($file->path, (string) $file->content);
        }
        $zip->close();

        return response()
            ->download($path, Str::slug($assignment->name).'.zip')
            ->deleteFileAfterSend();
    }
}
