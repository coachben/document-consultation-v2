class PdfAnnotationSystem {
    constructor(config) {
        this.url = config.url;
        this.containerId = config.containerId;
        this.container = document.getElementById(this.containerId);
        this.documentIsLoaded = false;

        // State
        this.pdfDoc = null;
        this.scale = 1.5;
        this.currentTool = 'select'; // select, hand, highlight, underline, strike, text, note
        this.annotations = config.annotations || [];

        // Callbacks
        this.onAnnotationCreate = config.onAnnotationCreate || (() => { });
        this.onTextSelect = config.onTextSelect || (() => { });

        this.init();
    }

    init() {
        // Expose global interaction updater for Alpine
        window.updateInteractionState = (tool) => this.setTool(tool);

        // Listen for internal events
        window.addEventListener('scale-change', (e) => this.setScale(e.detail.scale));
        window.addEventListener('render-highlight', (e) => this.handleOptimisticRender(e));

        // Render
        this.render();
    }

    setTool(tool) {
        this.currentTool = tool;
        this.updateCursorState();
    }

    setScale(newScale) {
        this.scale = newScale;
        if (this.renderTimeout) clearTimeout(this.renderTimeout);
        this.renderTimeout = setTimeout(() => this.render(), 100);
    }

    updateCursorState() {
        const layers = document.querySelectorAll('.annotation-layer');
        const textLayers = document.querySelectorAll('.textLayer');

        document.body.classList.remove('cursor-grab', 'cursor-text', 'cursor-copy', 'cursor-default');

        // Reset pointer events
        textLayers.forEach(l => l.style.pointerEvents = 'none');
        layers.forEach(l => l.style.pointerEvents = 'none');

        switch (this.currentTool) {
            case 'hand':
                document.body.style.cursor = 'grab';
                break;
            case 'select':
            case 'highlight':
            case 'underline':
            case 'strike':
                document.body.style.cursor = 'text';
                textLayers.forEach(l => l.style.pointerEvents = 'auto');
                break;
            case 'note':
            case 'text':
                document.body.style.cursor = 'copy'; // Or text cursor for Free Text
                layers.forEach(l => {
                    l.style.pointerEvents = 'auto'; // Capture clicks
                    l.classList.add('cursor-copy');
                });
                break;
            default:
                document.body.style.cursor = 'default';
        }
    }

    async render() {
        this.container.innerHTML = '';
        const loadingTask = pdfjsLib.getDocument(this.url);
        this.pdfDoc = await loadingTask.promise;
        this.documentIsLoaded = true;

        for (let pageNum = 1; pageNum <= this.pdfDoc.numPages; pageNum++) {
            await this.renderPage(pageNum);
        }

        this.updateCursorState();
    }

    async renderPage(pageNum) {
        const page = await this.pdfDoc.getPage(pageNum);
        const viewport = page.getViewport({ scale: this.scale });

        // Page Wrapper
        const pageDiv = document.createElement('div');
        pageDiv.className = 'relative mb-6 shadow-md group bg-white mx-auto';
        pageDiv.style.width = viewport.width + 'px';
        pageDiv.style.height = viewport.height + 'px';
        pageDiv.setAttribute('data-page-number', pageNum);
        this.container.appendChild(pageDiv);

        // Canvas Layer
        const canvas = document.createElement('canvas');
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        pageDiv.appendChild(canvas);

        await page.render({
            canvasContext: canvas.getContext('2d'),
            viewport: viewport
        }).promise;

        // Text Layer
        const textLayerDiv = document.createElement('div');
        textLayerDiv.className = 'textLayer absolute inset-0';
        textLayerDiv.style.setProperty('--scale-factor', this.scale);
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

        // Render Existing Annotations
        this.renderAnnotationsForPage(annotationLayer, pageNum);

        // Event: Click (for Note/Text tools)
        pageDiv.addEventListener('click', (e) => this.handlePageClick(e, pageDiv, pageNum, annotationLayer));

        // Event: Mouseup (for Text Selection tools)
        // We attach this globally or to the container, but here we can just ensure the document listener exists.
        // Actually, let's attach a specific listener to the text layer or just rely on document bubbling.
    }

    renderAnnotationsForPage(layer, pageNum) {
        const pageAnns = this.annotations.filter(a => a.page_number == pageNum);
        pageAnns.forEach(a => {
            if (a.type === 'highlight' || a.type === 'underline' || a.type === 'strike') {
                this.renderHighlight(layer, a);
            } else {
                this.renderNoteMarker(layer, a);
            }
        });
    }

    renderNoteMarker(layer, annotation) {
        const marker = document.createElement('div');
        // Simple Note Marker
        marker.className = 'absolute bg-blue-500 border-2 border-white w-6 h-6 rounded-full shadow-lg flex items-center justify-center text-white text-xs transform -translate-x-1/2 -translate-y-1/2 hover:scale-110 transition cursor-pointer pointer-events-auto z-10';
        marker.style.left = annotation.x_coordinate + '%';
        marker.style.top = annotation.y_coordinate + '%';

        // Icon based on type? For now generic 'note' icon
        marker.innerHTML = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>';
        marker.title = annotation.content;

        layer.appendChild(marker);
    }

    renderHighlight(layer, annotation) {
        if (!annotation.meta || !annotation.meta.rects) return;

        const type = annotation.type || 'highlight';

        annotation.meta.rects.forEach(rect => {
            const el = document.createElement('div');
            el.className = 'absolute pointer-events-none';
            el.style.left = rect.x + '%';
            el.style.top = rect.y + '%';
            el.style.width = rect.w + '%';
            el.style.height = rect.h + '%';
            el.title = annotation.content;

            if (type === 'highlight') {
                el.classList.add('bg-yellow-300', 'opacity-40', 'mix-blend-multiply');
            } else if (type === 'underline') {
                el.style.borderBottom = '2px solid red'; // Customize color?
            } else if (type === 'strike') {
                // Strike is harder with a div border, easiest is a specific line element or background linear-gradient
                el.style.background = 'linear-gradient(transparent 45%, red 45%, red 55%, transparent 55%)';
            }

            layer.appendChild(el);
        });
    }

    handlePageClick(e, pageDiv, pageNum, layer) {
        if (!['note', 'text'].includes(this.currentTool)) return;
        if (e.target.closest('.cursor-pointer')) return; // Clicked existing marker

        const rect = pageDiv.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width * 100;
        const y = (e.clientY - rect.top) / rect.height * 100;

        // For 'Text' tool, ideally we show an input field at that position.
        // For now, we'll keep the prompt pattern but prepare for 'Text' distinction.
        const content = prompt(this.currentTool === 'text' ? 'Enter text:' : 'Enter note:');

        if (content) {
            this.onAnnotationCreate({
                pageNum, x, y, content, type: 'note' // Default to note for now, can be 'text' later
            });

            // Optimistic Render
            this.renderNoteMarker(layer, { x_coordinate: x, y_coordinate: y, content });
        }
    }

    handleOptimisticRender(e) {
        if (e.detail.layer && e.detail.annotation) {
            this.renderHighlight(e.detail.layer, e.detail.annotation);
        }
    }

    // Global MouseUp for Selection
    handleSelection() {
        if (!['select', 'highlight', 'underline', 'strike'].includes(this.currentTool)) return;

        const selection = window.getSelection();
        if (selection.isCollapsed) return;

        const range = selection.getRangeAt(0);
        const startNode = range.startContainer.parentNode;
        const pageDiv = startNode.closest('.group');

        if (!pageDiv) return;

        const pageNum = parseInt(pageDiv.getAttribute('data-page-number'));
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
            this.onTextSelect({
                text,
                rects: pdfRects,
                pageNum,
                mainRect: pdfRects[0],
                tool: this.currentTool // Pass the active tool
            });
        }
    }
}

// Bind Global Listener
document.addEventListener('mouseup', () => {
    if (window.pdfApp) window.pdfApp.handleSelection();
});
