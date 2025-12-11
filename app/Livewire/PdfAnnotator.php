<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Annotation;
use Illuminate\Support\Collection;

class PdfAnnotator extends Component
{
    public $documentPath = 'sample.pdf';
    public $annotations = [];
    public $newAnnotationContent = '';

    public function mount()
    {
        $this->loadAnnotations();
    }

    public function loadAnnotations()
    {
        $this->annotations = Annotation::where('document_path', $this->documentPath)->get()->toArray();
    }

    public function saveAnnotation($page, $x, $y, $content, $type = 'note', $meta = [])
    {
        \Illuminate\Support\Facades\Log::info('saveAnnotation called', [
            'page' => $page,
            'x' => $x,
            'y' => $y,
            'content' => $content,
            'type' => $type,
            'meta' => $meta
        ]);

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
