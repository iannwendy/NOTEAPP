<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoteShare extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'note_id',
        'user_id',
        'permission'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'permission' => 'string'
    ];

    /**
     * Get the note that is shared.
     */
    public function note()
    {
        return $this->belongsTo(Note::class);
    }

    /**
     * Get the user that the note is shared with.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
