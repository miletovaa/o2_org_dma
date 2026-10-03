<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * A reference document (protocol, certificate, delivery note, …) stored once and attachable
 * to any number of samples. Files live on the private `local` disk and are only served through
 * the authenticated reference-files.download route.
 */
class ReferenceFile extends Model
{
    public const DISK = 'local';
    public const DIRECTORY = 'reference-files';

    protected $fillable = [
        'original_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected static function booted(): void
    {
        static::deleted(fn (ReferenceFile $file) => Storage::disk(self::DISK)->delete($file->path));
    }

    public static function storeUpload(UploadedFile $upload): self
    {
        return static::create([
            'original_name' => $upload->getClientOriginalName(),
            'path' => $upload->store(self::DIRECTORY, self::DISK),
            'mime_type' => $upload->getMimeType(),
            'size' => $upload->getSize() ?: 0,
            'uploaded_by' => Auth::id(),
        ]);
    }

    /** A user sees files they uploaded plus any file attached to a sample they can view. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('samples', 'view')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('uploaded_by', $user->id)
                ->orWhereHas('samples', fn (Builder $s) => $s->visibleTo($user));
        });
    }

    public function samples(): BelongsToMany
    {
        return $this->belongsToMany(Sample::class)->withTimestamps();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        $size = (float) $this->size;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024 || $unit === 'GB') {
                return ($unit === 'B' ? (int) $size : round($size, 1)) . ' ' . $unit;
            }
            $size /= 1024;
        }

        return (string) $this->size;
    }
}
