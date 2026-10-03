<?php

namespace App\Http\Controllers;

use App\Models\ReferenceFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReferenceFileController extends Controller
{
    public function download(ReferenceFile $referenceFile): StreamedResponse
    {
        abort_unless(ReferenceFile::visibleTo(Auth::user())->whereKey($referenceFile->id)->exists(), 404);
        abort_unless(Storage::disk(ReferenceFile::DISK)->exists($referenceFile->path), 404);

        return Storage::disk(ReferenceFile::DISK)->download($referenceFile->path, $referenceFile->original_name);
    }
}
