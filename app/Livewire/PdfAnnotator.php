<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Annotation;
use App\Models\Comment;
use App\Models\Vote;
use Illuminate\Support\Collection;

class PdfAnnotator extends Component
{
    public $documentPath = 'sample.pdf';
    public $annotations = [];
    public $newAnnotationContent = '';

    public $commentBody = []; // Keyed by annotation ID

    public function mount()
    {
        $this->loadAnnotations();
    }

    public function loadAnnotations()
    {
        $this->annotations = Annotation::where('document_path', $this->documentPath)
            ->with(['comments.user', 'votes'])
            ->latest()
            ->get()
            ->map(function ($annotation) {
                // Manually append accessors to array
                $annotation->setAttribute('score', $annotation->score);
                $annotation->setAttribute('user_vote', $annotation->votes->where('user_id', $this->getUserId())->first()?->type);
                return $annotation;
            })
            ->toArray();
    }

    public function getUserId()
    {
        if (auth()->check()) {
            return auth()->id();
        }

        // Ensure a fallback user exists for guests/demos
        return \App\Models\User::firstOrCreate(
            ['email' => 'guest@example.com'],
            ['name' => 'Guest User', 'password' => bcrypt('password')]
        )->id;
    }

    public function toggleVote($annotationId, $type)
    {
        $userId = $this->getUserId();
        $annotation = Annotation::find($annotationId);

        $existing = $annotation->votes()->where('user_id', $userId)->first();

        if ($existing) {
            if ($existing->type === $type) {
                $existing->delete(); // Untoggle
            } else {
                $existing->update(['type' => $type]);
            }
        } else {
            $annotation->votes()->create([
                'user_id' => $userId,
                'type' => $type
            ]);
        }

        $this->loadAnnotations();
    }

    public function addComment($annotationId)
    {
        if (empty($this->commentBody[$annotationId]))
            return;

        Comment::create([
            'user_id' => $this->getUserId(),
            'annotation_id' => $annotationId,
            'body' => $this->commentBody[$annotationId]
        ]);

        $this->commentBody[$annotationId] = '';
        $this->loadAnnotations();
    }

    public function saveAnnotation($page, $x, $y, $content, $type = 'note', $meta = [])
    {
        // ... (logging omitted for brevity)

        Annotation::create([
            'document_path' => $this->documentPath,
            'page_number' => $page,
            'x_coordinate' => $x,
            'y_coordinate' => $y,
            'content' => $content,
            'type' => $type,
            'meta' => $meta,
        ]);

        $this->loadAnnotations();
    }

    public function deleteAnnotation($id)
    {
        Annotation::find($id)->delete();
        $this->loadAnnotations();
    }

    public function render()
    {
        return view('livewire.pdf-annotator');
    }
}
