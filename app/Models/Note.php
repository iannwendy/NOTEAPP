<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Note extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'color',
        'pinned',
        'pinned_at',
        'is_password_protected',
        'password'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'pinned' => 'boolean',
        'is_password_protected' => 'boolean',
    ];

    /**
     * Set the note's password.
     *
     * @param string $value
     * @return void
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = $value ? Hash::make($value) : null;
    }

    /**
     * Check if the provided password is correct.
     *
     * @param string $password
     * @return bool
     */
    public function checkPassword($password)
    {
        return Hash::check($password, $this->password);
    }

    /**
     * Get the user that owns the note.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attachments for the note.
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Get the labels for this note.
     */
    public function labels()
    {
        return $this->belongsToMany(Label::class)->withTimestamps();
    }
    
    /**
     * Get the users that this note is shared with.
     */
    public function sharedWith()
    {
        return $this->belongsToMany(User::class, 'note_shares')
                    ->withPivot('permission')
                    ->withTimestamps();
    }

    /**
     * Get the share records for this note.
     */
    public function shares()
    {
        return $this->hasMany(NoteShare::class);
    }

    /**
     * Check if note is shared with a specific user.
     *
     * @param int $userId
     * @return bool
     */
    public function isSharedWithUser($userId)
    {
        return $this->sharedWith()->where('users.id', $userId)->exists();
    }

    /**
     * Get the permission for a shared user.
     *
     * @param int $userId
     * @return string|null
     */
    public function getPermissionForUser($userId)
    {
        $share = $this->sharedWith()->where('users.id', $userId)->first();
        return $share ? $share->pivot->permission : null;
    }
    
    /**
     * Get the created_at timestamp formatted in local timezone
     * 
     * @param string $format
     * @return string
     */
    public function getFormattedCreatedAt($format = 'M d, Y h:i A')
    {
        return $this->created_at->format($format);
    }
    
    /**
     * Get the updated_at timestamp formatted in local timezone
     * 
     * @param string $format
     * @return string
     */
    public function getFormattedUpdatedAt($format = 'M d, Y h:i A')
    {
        return $this->updated_at->format($format);
    }
}
