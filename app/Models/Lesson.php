<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Lesson extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_preview' => 'boolean'];

    public const TYPES = [
        'text' => 'Text',
        'video' => 'Video',
        'download' => 'Download',
        'quiz' => 'Quiz',
        'exam' => 'Final exam',
    ];

    public function course() { return $this->belongsTo(Course::class); }
    public function chapter() { return $this->belongsTo(Chapter::class); }
    public function quiz() { return $this->hasOne(Quiz::class); }

    /** Editor HTML is sanitized before it is stored. */
    public function setContentAttribute(?string $value): void { $this->attributes['content'] = \App\Support\RichText::clean($value); }

    public function isAssessment(): bool { return in_array($this->type, ['quiz', 'exam'], true); }
    public function typeLabel(): string { return self::TYPES[$this->type] ?? ucfirst($this->type); }

    public function renderedContent(): string
    {
        return $this->content ? (string) \App\Support\RichText::html($this->content) : '';
    }

    /** Converts YouTube/Vimeo links into embeddable URLs; returns null for direct files. */
    public function embedUrl(): ?string
    {
        $url = (string) $this->video_url;
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }
        return null;
    }

    public function videoFileUrl(): ?string
    {
        if ($this->video_path) return Storage::url($this->video_path);
        if ($this->video_url && ! $this->embedUrl()) return $this->video_url;
        return null;
    }
}
