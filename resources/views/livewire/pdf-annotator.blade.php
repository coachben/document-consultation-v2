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
            
            <!-- Text Annotation Tools -->
            <div class="flex items-center gap-1 pr-4 border-r border-gray-300">
                <!-- Highlight -->
                <button @click="setTool('highlight')"
                    :class="activeTool === 'highlight' ? 'bg-gray-200 text-blue-600' : 'text-gray-600 hover:bg-gray-200'"
                    class="p-2 rounded transition" title="Highlight">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M3 21h18" stroke-linecap="round" /> <!-- Line at bottom like a marker underline -->
                    </svg>
                </button>
                
                <!-- Underline -->
                <button @click="setTool('underline')"
                    :class="activeTool === 'underline' ? 'bg-gray-200 text-blue-600' : 'text-gray-600 hover:bg-gray-200'"
                    class="p-2 rounded transition group" title="Underline">
                     <div class="flex flex-col items-center justify-center">
                        <span class="font-serif font-bold text-lg leading-none">U</span>
                        <div class="h-0.5 w-4 bg-current mt-0.5"></div>
                    </div>
                </button>

                <!-- Strike -->
                <button @click="setTool('strike')"
                    :class="activeTool === 'strike' ? 'bg-gray-200 text-blue-600' : 'text-gray-600 hover:bg-gray-200'"
                    class="p-2 rounded transition relative" title="Strikethrough">
                     <span class="font-serif font-bold text-lg leading-none relative">
                        S
                        <div class="absolute top-1/2 left-0 w-full h-0.5 bg-current transform -translate-y-1/2"></div>
                     </span>
                </button>

                <!-- Free Text -->
                <button @click="setTool('text')"
                    :class="activeTool === 'text' ? 'bg-gray-200 text-blue-600' : 'text-gray-600 hover:bg-gray-200'"
                    class="p-2 rounded transition" title="Free Text">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M7 8h10M12 7v9m-4 8h8" stroke-linecap="round" stroke-linejoin="round"/>
                        <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="1.5" stroke-dasharray="4 2"/> <!-- Box hint -->
                    </svg>
                </button>
            </div>

            <!-- Comment/Note Tools -->
            <div class="flex items-center gap-1 pr-4 border-r border-gray-300">
                <button @click="setTool('note')"
                    :class="activeTool === 'note' ? 'bg-gray-200 text-blue-600' : 'text-gray-600 hover:bg-gray-200'"
                    class="p-2 rounded transition" title="Sticky Note">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 2h8a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z" />
                        <path d="M9 2v4h6" /> <!-- Fold style -->
                        <path d="M8 10h8M8 14h6" stroke-linecap="round" />
                    </svg>
                </button>
            </div>

            <!-- History Tools -->
            <div class="flex items-center gap-1">
                <button class="p-2 rounded text-gray-600 hover:bg-gray-200 transition disabled:opacity-50" title="Undo">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                    </svg>
                </button>
                <button class="p-2 rounded text-gray-600 hover:bg-gray-200 transition disabled:opacity-50" title="Redo">
                     <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"></path>
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

        <script src="{{ asset('js/pdf-annotation-system.js') }}"></script>
        <script>
            document.addEventListener('livewire:initialized', () => {
                const config = {
                    url: '{{ asset($documentPath) }}',
                    containerId: 'pdf-container',
                    annotations: @json($annotations),
                    onAnnotationCreate: (data) => {
                        @this.saveAnnotation(data.pageNum, data.x, data.y, data.content, data.type);
                    },
                    onTextSelect: (data) => {
                         // Open Modal using Alpine helper
                        if (window.openAnnotationModal) {
                            window.openAnnotationModal(data);
                        }
                    }
                };

                // Initialize System
                window.pdfApp = new PdfAnnotationSystem(config);

                // Listen for Livewire updates to refresh annotations if needed?
                // For now, optimistic UI handles immediate feedback.
            });
        </script>
    </div>
</div>