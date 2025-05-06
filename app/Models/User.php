<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'avatar',
        'bio',
        'password',
        'preferences',
        'is_activated'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'json',
            'is_activated' => 'boolean',
        ];
    }

    /**
     * Get the notes for the user.
     */
    public function notes()
    {
        return $this->hasMany(Note::class);
    }
    
    /**
     * Get the labels for the user.
     */
    public function labels()
    {
        return $this->hasMany(Label::class);
    }
    
    /**
     * Get notes shared with this user.
     */
    public function sharedNotes()
    {
        return $this->belongsToMany(Note::class, 'note_shares')
                    ->withPivot('permission')
                    ->withTimestamps();
    }
    
    /**
     * Get the avatar URL.
     */
    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            $path = 'avatars/' . $this->avatar;
            // Check if the file exists in the storage
            if (Storage::disk('public')->exists($path)) {
                return asset('storage/' . $path);
            }
        }
        
        // Return default avatar if no avatar or file doesn't exist
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=random&color=fff';
    }
    
    /**
     * Get the created_at timestamp formatted in local timezone
     * 
     * @param string $format
     * @return string
     */
    public function getFormattedCreatedAt($format = 'F j, Y')
    {
        return $this->created_at->format($format);
    }
    
    /**
     * Get the email_verified_at timestamp formatted in local timezone
     * 
     * @param string $format
     * @return string|null
     */
    public function getFormattedVerifiedAt($format = 'F j, Y')
    {
        return $this->email_verified_at ? $this->email_verified_at->format($format) : null;
    }
}
