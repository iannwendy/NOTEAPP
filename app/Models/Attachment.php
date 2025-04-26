<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'note_id',
        'filename',
        'original_filename',
        'file_type',
        'file_path',
        'file_size'
    ];

    /**
     * Get the note that owns the attachment.
     */
    public function note()
    {
        return $this->belongsTo(Note::class);
    }
}
