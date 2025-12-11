# Annotation System Documentation

## 1. System Overview
The Annotation System is a full-stack feature within the Consul application that enables users to view PDF documents, highlight text with native browser selection, and add spatial sticky notes. It utilizes **Mozilla PDF.js** for rendering and an **invisible Text Layer** for interaction.

## 2. Infrastructure & Database

### 2.1 Database Schema
We extended the `annotations` table to support different annotation types (`highlight`, `note`) and flexible metadata storage via a JSON column.

**Migration:** `2025_12_10_132444_add_type_and_meta_to_annotations_table.php`
```php
public function up(): void
{
    Schema::table('annotations', function (Blueprint $table) {
        $table->string('type')->default('note'); // 'highlight' or 'note'
        $table->json('meta')->nullable();        // Stores coords, rating, note text
    });
}
```

### 2.2 Eloquent Model
The `Annotation` model was updated to handle mass assignment and automatic JSON casting.

**Model:** `app/Models/Annotation.php`
```php
class Annotation extends Model
{
    protected $fillable = [
        'document_path',
        'page_number',
        'x_coordinate',
        'y_coordinate',
        'content',
        'type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array', // Automatically decodes JSON from DB
    ];
}
```

## 3. Backend Logic (Livewire)

The `PdfAnnotator` component handles the business logic for saving and deleting annotations. It accepts the percentage-based coordinates and metadata from the frontend.

**Component:** `app/Livewire/PdfAnnotator.php`
```php
public function saveAnnotation($page, $x, $y, $content, $type = 'note', $meta = [])
{
    Annotation::create([
        'document_path' => $this->documentPath,
        'page_number' => $page,
        'x_coordinate' => $x,
        'y_coordinate' => $y,
        'content' => $content, // Selected text or note content
        'type' => $type,
        'meta' => $meta,       // Contains rects, rating, user notes
    ]);

    $this->loadAnnotations(); // Refresh the list
}

public function deleteAnnotation($id)
{
    Annotation::find($id)->delete();
    $this->loadAnnotations();
}
```

## 4. Frontend Implementation

### 4.1 PDF Rendering & Text Layer
We use PDF.js to render both the Canvas (visuals) and the Text Layer (selection interaction).

**File:** `resources/views/livewire/pdf-annotator.blade.php`

```javascript
// Inside renderPdf() loop
// 1. Render Canvas
await page.render(renderContext).promise;

// 2. Render Text Layer (Invisible, matches text positions)
const textLayerDiv = document.createElement('div');
textLayerDiv.className = 'textLayer absolute inset-0';
pdfjsLib.renderTextLayer({
    textContentSource: await page.getTextContent(),
    container: textLayerDiv,
    viewport: viewport,
    textDivs: []
});
```

### 4.2 Handling Highlights & Modal
Instead of saving immediately upon selection, we intercept the `mouseup` event to open a modal.

```javascript
document.addEventListener('mouseup', async () => {
    if (isNoteMode) return; // Ignore if in "Add Note" mode

    const selection = window.getSelection();
    if (selection.isCollapsed) return;

    // 1. Calculate Bounding Box relative to Page
    const range = selection.getRangeAt(0);
    const pageDiv = range.startContainer.parentNode.closest('.group');
    const pageRect = pageDiv.getBoundingClientRect();
    const clientRects = Array.from(range.getClientRects());
    
    // Convert logic pixels to Percentage (%)
    const pdfRects = clientRects.map(rect => ({
        x: (rect.left - pageRect.left) / pageRect.width * 100,
        y: (rect.top - pageRect.top) / pageRect.height * 100,
        w: rect.width / pageRect.width * 100,
        h: rect.height / pageRect.height * 100
    }));

    // 2. Open Modal instead of saving directly
    const text = selection.toString();
    if (text) {
        // Dispatch event to show Alpine.js Modal
        window.dispatchEvent(new CustomEvent('open-modal', { 
            detail: {
                text: text,
                rects: pdfRects,
                pageNum: pageNum,
                mainRect: pdfRects[0] // Primary anchor point
            } 
        }));
    }
});
```

### 4.3 Alpine.js Modal Logic
The modal captures the user's input and calls the Livewire backend.

```html
<div x-data="{ 
    showModal: false, 
    rating: 0, 
    note: '', 
    // ... 
    submitAnnotation() {
        $wire.saveAnnotation(
            this.tempSelection.pageNum,
            this.tempSelection.mainRect.x,
            this.tempSelection.mainRect.y,
            this.tempSelection.text,
            'highlight',
            { 
                rects: this.tempSelection.rects, 
                note: this.note, 
                rating: this.rating 
            }
        );
        this.closeModal();
    }
}" @open-modal.window="tempSelection = $event.detail; showModal = true">
```

### 4.4 Spatial Sticky Notes
An "Add Note" mode allows users to click anywhere (that isn't text) to leave a note.

```javascript
// Toggle Mode
addNoteBtn.addEventListener('click', () => {
    isNoteMode = !isNoteMode;
    // Toggles 'pointer-events-none' on annotation layer to capture clicks
    updateModeUI(); 
});

// Click Handler
pageDiv.addEventListener('click', (e) => {
    if (!isNoteMode) return;
    
    // Calculate % coordinates
    const x = (e.clientX - rect.left) / rect.width * 100;
    const y = (e.clientY - rect.top) / rect.height * 100;
    
    const text = prompt('Enter note:'); // Simple prompt for v1
    if (text) {
        @this.saveAnnotation(pageNum, x, y, text, 'note');
    }
});
```

## 5. Summary of Features
- **Highlights**: Yellow transparent overlay, supports multi-line selection.
- **Notes**: Blue markers placed at specific X/Y coordinates.
- **Rich Data**: Highlights include Star Ratings (1-5) and detailed text notes.
- **Sidebar**: Real-time list of all annotations with Delete functionality.
