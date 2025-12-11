<div class="h-screen flex flex-col overflow-hidden" x-data="{ 
    showModal: false, 
    rating: 0, 
    note: '', 
    tempSelection: null,
    scale: 1.5,
    activeTool: 'select', // select, hand, highlight, note
    
    init() {
        this.$watch('scale', val => {
            window.dispatchEvent(new CustomEvent('scale-change', { detail: { scale: val } }));
        });
        this.$watch('activeTool', val => {
             updateInteractionState(val);
        });
        window.openAnnotationModal = (data) => {
            this.tempSelection = data;
            this.showModal = true;
        };
    },

    zoomIn() { if(this.scale < 3.0) this.scale += 0.25; },
    zoomOut() { if(this.scale > 0.5) this.scale -= 0.25; },
    setTool(tool) { this.activeTool = tool; },

    submitAnnotation() {
        if (!this.tempSelection) return;
        
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

        // Optimistic UI for Sidebar (basic list append happens via Livewire re-render usually, 
        // but we can rely on Livewire here for sidebar as it's outside the PDF canvs)
        // Optimistic UI for Highlight on PDF
        const pageDiv = document.querySelector(`.group[data-page-number='${this.tempSelection.pageNum}']`);
        if (pageDiv) {
             const annotationLayer = pageDiv.querySelector('.annotation-layer');
             // dispatch event or call global function? 
             // accessing component scope from Alpine is tricky for custom JS functions defined below.
             // We'll trust the Livewire re-render or add a custom event dispatch.
             window.dispatchEvent(new CustomEvent('render-highlight', { 
                detail: { 
                    layer: annotationLayer,
                    annotation: {
                        content: this.tempSelection.text,
                        meta: { rects: this.tempSelection.rects }
                    }
                }
             }));
        }

        this.closeModal();
    },
    closeModal() {
        this.showModal = false;
        this.note = '';
        this.rating = 0;
        this.tempSelection = null;
        window.getSelection().removeAllRanges();
    }
}" @open-modal.window="tempSelection = $event.detail; showModal = true">

    <!-- Toolbar Section -->
    <div class="bg-white border-b border-gray-300 shadow-sm z-50 flex-none select-none">

        <!-- Top Row: Global Controls -->
        <div class="h-14 px-4 flex items-center justify-between border-b border-gray-200 bg-gray-50">
            <!-- Left: Zoom & Sidebar -->
            <div class="flex items-center gap-4">
                <button class="text-gray-500 hover:text-gray-700 p-1.5 rounded hover:bg-gray-200 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div class="h-6 w-px bg-gray-300 mx-1"></div>
                <div class="flex items-center bg-white border border-gray-300 rounded overflow-hidden">
                    <button @click="zoomOut()"
                        class="px-3 py-1 hover:bg-gray-100 text-gray-600 border-r border-gray-300">−</button>
                    <span class="px-3 py-1 text-sm font-mono min-w-[3.5rem] text-center"
                        x-text="Math.round(scale * 100) + '%'">150%</span>
                    <button @click="zoomIn()"
                        class="px-3 py-1 hover:bg-gray-100 text-gray-600 border-l border-gray-300">+</button>
                </div>
            </div>

            <!-- Center: Tabs -->
            <div class="flex items-center gap-1 bg-gray-200 p-1 rounded-lg">
                <button
                    class="px-4 py-1 text-sm rounded-md font-medium transition text-gray-500 hover:text-gray-700">View</button>
                <button
                    class="px-4 py-1 text-sm rounded-md font-medium transition bg-white shadow text-blue-600">Annotate</button>
                <button
                    class="px-4 py-1 text-sm rounded-md font-medium transition text-gray-500 hover:text-gray-700">Shapes</button>
                <button
                    class="px-4 py-1 text-sm rounded-md font-medium transition text-gray-500 hover:text-gray-700">Insert</button>
            </div>

            <!-- Right: Pan/Select Modes -->
            <div class="flex items-center gap-2 bg-white border border-gray-300 rounded p-1">
                <button @click="setTool('hand')"
                    :class="activeTool === 'hand' ? 'bg-blue-100 text-blue-600' : 'text-gray-500 hover:bg-gray-100'"
                    class="p-2 rounded transition" title="Pan Tool (Hand)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11">
                        </path>
                    </svg>
                </button>
                <button @click="setTool('select')"
                    :class="activeTool === 'select' || activeTool === 'highlight' ? 'bg-blue-100 text-blue-600' : 'text-gray-500 hover:bg-gray-100'"
                    class="p-2 rounded transition" title="Selection Tool">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122">
                        </path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Bottom Row: Context Tools (Annotate) -->
        <div class="h-12 px-4 flex items-center bg-gray-50 gap-2 shadow-inner border-b border-gray-200" x-show="true">
            <!-- x-show="activeTab === 'annotate'" if we had tabs state -->

            <!-- Text Annotation Tools -->
            <div class="flex items-center gap-1 pr-4 border-r border-gray-300">
                <button @click="setTool('highlight')"
                    :class="activeTool === 'highlight' ? 'bg-gray-200 text-blue-600' : 'text-gray-600 hover:bg-gray-200'"
                    class="p-2 rounded transition" title="Highlight Text">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z">
                        </path>
                    </svg>
                </button>
                <button @click="setTool('underline')"
                    :class="activeTool === 'underline' ? 'bg-gray-200 text-blue-600' : 'text-gray-600 hover:bg-gray-200'"
                    class="p-2 rounded transition" title="Underline Text">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 19h16M4 5a8 8 0 0116 0"></path>
                    </svg> <!-- Approximate U icon -->
                </button>
                <button @click="setTool('strike')"
                    :class="activeTool === 'strike' ? 'bg-gray-200 text-blue-600' : 'text-gray-600 hover:bg-gray-200'"
                    class="p-2 rounded transition" title="Strikethrough Text">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"></path>
                    </svg> <!-- Strike icon -->
                </button>
                <button @click="setTool('text')"
                    :class="activeTool === 'text' ? 'bg-gray-200 text-blue-600' : 'text-gray-600 hover:bg-gray-200'"
                    class="p-2 rounded transition" title="Free Text">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M12 7v9m-4 8h8">
                        </path>
                    </svg>
                </button>
            </div>

            <!-- Comment/Note Tools -->
            <div class="flex items-center gap-1 pr-4 border-r border-gray-300">
                <button @click="setTool('note')"
                    :class="activeTool === 'note' ? 'bg-gray-200 text-blue-600' : 'text-gray-600 hover:bg-gray-200'"
                    class="p-2 rounded transition" title="Add Note">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                        </path>
                    </svg>
                </button>
            </div>

            <!-- History Tools -->
            <div class="flex items-center gap-1">
                <button class="p-2 rounded text-gray-600 hover:bg-gray-200 transition disabled:opacity-50" title="Undo">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                    </svg>
                </button>
                <button class="p-2 rounded text-gray-600 hover:bg-gray-200 transition disabled:opacity-50" title="Redo">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex overflow-hidden">
        <!-- Main Content: PDF (Left) -->
        <div class="flex-1 bg-gray-500 relative flex flex-col items-center p-8 overflow-auto" id="main-scroll-area">

            <div id="pdf-container" wire:ignore
                class="shadow-2xl relative transition-transform origin-top duration-200 ease-out"></div>
        </div>

        <!-- Sidebar: Annotations (Right) -->
        <div class="w-80 bg-white border-l border-gray-200 flex flex-col shadow-xl z-10">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50">
                <h2 class="font-bold text-gray-700">Annotations</h2>
                <span class="bg-gray-200 text-gray-600 text-xs px-2 py-1 rounded-full">{{ count($annotations) }}</span>
            </div>

            <div class="flex-1 overflow-auto p-4 space-y-3">
                @forelse($annotations as $annotation)
                    <div
                        class="group bg-white border border-gray-200 rounded-lg p-3 hover:border-blue-400 hover:shadow-md transition relative">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2 mb-1">
                                @if($annotation['type'] === 'highlight')
                                    <span
                                        class="bg-yellow-100 text-yellow-700 text-[10px] px-1.5 py-0.5 rounded uppercase font-bold tracking-wider">Highlight</span>
                                @else
                                    <span
                                        class="bg-blue-100 text-blue-700 text-[10px] px-1.5 py-0.5 rounded uppercase font-bold tracking-wider">Note</span>
                                @endif

                                <!-- Stars -->
                                @if(isset($annotation['meta']['rating']) && $annotation['meta']['rating'] > 0)
                                    <div class="flex text-yellow-400 text-xs">
                                        @for($i = 0; $i < $annotation['meta']['rating']; $i++) ★ @endfor
                                    </div>
                                @endif
                            </div>
                            <button wire:click="deleteAnnotation({{ $annotation['id'] }})"
                                class="text-gray-300 hover:text-red-500 transition p-1 rounded hover:bg-red-50"
                                title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                    </path>
                                </svg>
                            </button>
                        </div>

                        <p class="text-sm text-gray-700 leading-relaxed break-words font-medium">
                            "{{ Str::limit($annotation['content'], 100) }}"
                        </p>

                        @if(isset($annotation['meta']['note']) && $annotation['meta']['note'])
                            <div class="mt-2 text-xs text-gray-500 bg-gray-50 p-2 rounded italic">
                                {{ $annotation['meta']['note'] }}
                            </div>
                        @endif

                        <div class="mt-1 text-xs text-gray-300 text-right">Page {{ $annotation['page_number'] }}</div>
                    </div>
                @empty
                    <div class="text-center py-10 text-gray-400">
                        <p class="text-sm">No annotations yet.</p>
                        <p class="text-xs mt-1">Select text to highlight</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Rating Modal -->
        <div x-show="showModal" style="display: none;"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm" x-transition>
            <div class="bg-white rounded-xl shadow-2xl p-6 w-96 transform transition-all" @click.away="closeModal()">
                <h3 class="text-lg font-bold text-gray-800 mb-2">Add Annotation</h3>

                <div class="bg-yellow-50 p-3 rounded mb-4 text-sm text-gray-600 italic border-l-4 border-yellow-300">
                    "<span
                        x-text="tempSelection ? (tempSelection.text.length > 50 ? tempSelection.text.substring(0,50) + '...' : tempSelection.text) : ''"></span>"
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Rating</label>
                    <div class="flex gap-1">
                        <template x-for="i in 5">
                            <button @click="rating = i"
                                class="text-2xl focus:outline-none transition transform hover:scale-110"
                                :class="rating >= i ? 'text-yellow-400' : 'text-gray-300'">★</button>
                        </template>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Note
                        (Optional)</label>
                    <textarea x-model="note"
                        class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        rows="3" placeholder="Add your thoughts..."></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button @click="closeModal()"
                        class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                    <button @click="submitAnnotation()"
                        class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded font-medium shadow-lg hover:shadow-xl transition">Save</button>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('livewire:initialized', () => {
                const url = '{{ asset($documentPath) }}';
                const container = document.getElementById('pdf-container');

                let pdfDoc = null;
                let currentScale = 1.5;
                let currentTool = 'select';

                // Listen for Scale Change from Alpine
                window.addEventListener('scale-change', (e) => {
                    handleScaleChange(e.detail.scale);
                });

                // Modal Open Helper
                function openModal(data) {
                    if (window.openAnnotationModal) {
                        window.openAnnotationModal(data);
                    } else {
                        // Retry once if Alpine isn't ready
                        setTimeout(() => { if (window.openAnnotationModal) window.openAnnotationModal(data); }, 100);
                    }
                }

                // Listen for Custom Render (Optimistic UI)
                window.addEventListener('render-highlight', (e) => {
                    if (e.detail.layer && e.detail.annotation) {
                        renderHighlight(e.detail.layer, e.detail.annotation);
                    }
                });

                // Global function for Alpine to call
                window.updateInteractionState = (tool) => {
                    currentTool = tool;
                    const layers = document.querySelectorAll('.annotation-layer');
                    const textLayers = document.querySelectorAll('.textLayer');

                    // Reset classes
                    document.body.classList.remove('cursor-grab', 'cursor-text', 'cursor-copy', 'cursor-default');

                    if (tool === 'hand') {
                        document.body.style.cursor = 'grab';
                        textLayers.forEach(l => l.style.pointerEvents = 'none');
                        layers.forEach(l => l.style.pointerEvents = 'none');
                    } else if (['select', 'highlight', 'underline', 'strike'].includes(tool)) {
                        document.body.style.cursor = 'text';
                        textLayers.forEach(l => l.style.pointerEvents = 'auto');
                        layers.forEach(l => l.style.pointerEvents = 'none');
                    } else if (['note', 'text'].includes(tool)) {
                        document.body.style.cursor = 'copy'; // Start/Text cursor
                        textLayers.forEach(l => l.style.pointerEvents = 'none');
                        layers.forEach(l => {
                            l.style.pointerEvents = 'auto';
                            l.classList.add('cursor-copy');
                        });
                    }
                };

                // Debounced Scale Handler
                let scaleTimeout;
                function handleScaleChange(newScale) {
                    currentScale = newScale;
                    clearTimeout(scaleTimeout);
                    scaleTimeout = setTimeout(() => {
                        renderPdf();
                    }, 100); // 100ms debounce
                }

                async function renderPdf() {
                    container.innerHTML = ''; // Clear existing
                    // Show Loading?

                    const loadingTask = pdfjsLib.getDocument(url);
                    pdfDoc = await loadingTask.promise;

                    for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
                        const page = await pdfDoc.getPage(pageNum);
                        const viewport = page.getViewport({ scale: currentScale });

                        // Wrapper
                        const pageDiv = document.createElement('div');
                        pageDiv.className = 'relative mb-6 shadow-md group bg-white';
                        pageDiv.style.width = viewport.width + 'px';
                        pageDiv.style.height = viewport.height + 'px';
                        pageDiv.setAttribute('data-page-number', pageNum);
                        container.appendChild(pageDiv);

                        // Canvas
                        const canvas = document.createElement('canvas');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        pageDiv.appendChild(canvas);

                        const renderContext = {
                            canvasContext: canvas.getContext('2d'),
                            viewport: viewport,
                        };
                        await page.render(renderContext).promise;

                        // Text Layer
                        const textLayerDiv = document.createElement('div');
                        textLayerDiv.className = 'textLayer absolute inset-0';
                        textLayerDiv.style.setProperty('--scale-factor', currentScale);
                        pageDiv.appendChild(textLayerDiv);

                        const textContent = await page.getTextContent();
                        pdfjsLib.renderTextLayer({
                            textContentSource: textContent,
                            container: textLayerDiv,
                            viewport: viewport,
                            textDivs: []
                        });

                        // Annotation Layer
                        const annotationLayer = document.createElement('div');
                        annotationLayer.className = 'annotation-layer absolute inset-0 pointer-events-none';
                        pageDiv.appendChild(annotationLayer);

                        // Render existing annotations
                        renderAnnotations(annotationLayer, pageNum, viewport.width, viewport.height);

                        // Note Creation Handler
                        // Attached to pageDiv to capture clicks when in 'note' mode
                        pageDiv.addEventListener('click', (e) => {
                            if (currentTool !== 'note' && currentTool !== 'text') return;

                            // Ignore if clicked on existing marker
                            if (e.target.closest('.cursor-pointer')) return;

                            const rect = pageDiv.getBoundingClientRect();
                            const x = (e.clientX - rect.left) / rect.width * 100;
                            const y = (e.clientY - rect.top) / rect.height * 100;

                            const text = prompt('Enter note:');
                            if (text) {
                                // Default type 'note' for both tools for now, or distinguish if backend supports 'text'
                                // The backend supports 'note', so we use that.
                                @this.saveAnnotation(pageNum, x, y, text, 'note');
                                renderNoteMarker(annotationLayer, {
                                    x_coordinate: x,
                                    y_coordinate: y,
                                    content: text
                                });
                                // Don't auto-switch tool, let user add multiple notes
                            }
                        });
                    }

                    // Re-apply current tool state to new elements
                    window.updateInteractionState(currentTool);
                }

                function renderAnnotations(layer, pageNum, width, height) {
                    const annotations = @json($annotations);
                    annotations.filter(a => a.page_number == pageNum).forEach(a => {
                        if (a.type === 'highlight') {
                            renderHighlight(layer, a);
                        } else {
                            renderNoteMarker(layer, a);
                        }
                    });
                }

                function renderNoteMarker(layer, annotation) {
                    const marker = document.createElement('div');
                    marker.className = 'absolute bg-blue-500 border-2 border-white w-6 h-6 rounded-full shadow-lg flex items-center justify-center text-white text-xs transform -translate-x-1/2 -translate-y-1/2 hover:scale-110 transition cursor-pointer pointer-events-auto z-10';
                    marker.style.left = annotation.x_coordinate + '%';
                    marker.style.top = annotation.y_coordinate + '%';
                    marker.innerHTML = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>';
                    marker.title = annotation.content;
                    layer.appendChild(marker);
                }

                function renderHighlight(layer, annotation) {
                    if (!annotation.meta || !annotation.meta.rects) return;
                    annotation.meta.rects.forEach(rect => {
                        const el = document.createElement('div');
                        el.className = 'absolute bg-yellow-300 opacity-40 mix-blend-multiply pointer-events-none';
                        el.style.left = rect.x + '%';
                        el.style.top = rect.y + '%';
                        el.style.width = rect.w + '%';
                        el.style.height = rect.h + '%';
                        el.title = annotation.content;
                        layer.appendChild(el);
                    });
                }

                // Handle Text Selection for Highlights
                document.addEventListener('mouseup', async () => {
                    // Allow selection in these modes
                    if (!['select', 'highlight', 'underline', 'strike'].includes(currentTool)) return;

                    const selection = window.getSelection();
                    if (selection.isCollapsed) return;

                    const range = selection.getRangeAt(0);
                    const startNode = range.startContainer.parentNode;

                    const pageDiv = startNode.closest('.group');
                    if (!pageDiv) return;

                    const pageNum = parseInt(pageDiv.getAttribute('data-page-number'));
                    if (!pageNum) return;

                    const pageRect = pageDiv.getBoundingClientRect();
                    const clientRects = Array.from(range.getClientRects());
                    const pdfRects = clientRects.map(rect => ({
                        x: (rect.left - pageRect.left) / pageRect.width * 100,
                        y: (rect.top - pageRect.top) / pageRect.height * 100,
                        w: rect.width / pageRect.width * 100,
                        h: rect.height / pageRect.height * 100
                    }));

                    const text = selection.toString();
                    if (text && pdfRects.length > 0) {
                        // Open Modal instead of saving
                        setTimeout(() => {
                            openModal({
                                text: text,
                                rects: pdfRects,
                                pageNum: pageNum,
                                mainRect: pdfRects[0]
                            });
                        }, 100);
                    }
                });

                renderPdf();
            });
        </script>
    </div>