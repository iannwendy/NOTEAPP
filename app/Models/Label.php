<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'color'
    ];
    
    /**
     * Get the user that owns the label.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the notes that belong to this label.
     */
    public function notes()
    {
        return $this->belongsToMany(Note::class)->withTimestamps();
    }
}
