// Formatting editor for long text fields. Any <textarea data-rich> becomes an editor with a
// small toolbar (bold, italic, underline, and a menu for the rest). The textarea stays in the
// form, hidden, and always holds the editor's HTML, so forms submit as before.
// The server sanitizes everything again (App\Support\RichText); this is only the editing UI.
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';

const icon = (path) => `<svg viewBox="0 0 20 20" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${path}</svg>`;

const BUTTONS = [
    { name: 'bold', label: 'Bold (Ctrl+B)', html: '<span class="font-extrabold">B</span>', run: (c) => c.toggleBold() },
    { name: 'italic', label: 'Italic (Ctrl+I)', html: '<span class="font-serif italic font-semibold">I</span>', run: (c) => c.toggleItalic() },
    { name: 'underline', label: 'Underline (Ctrl+U)', html: '<span class="font-semibold underline underline-offset-2">U</span>', run: (c) => c.toggleUnderline() },
];

const MENU = [
    { label: 'Normal text', active: (e) => e.isActive('paragraph'), run: (c) => c.setParagraph() },
    { label: 'Heading', active: (e) => e.isActive('heading', { level: 2 }), run: (c) => c.toggleHeading({ level: 2 }) },
    { label: 'Subheading', active: (e) => e.isActive('heading', { level: 3 }), run: (c) => c.toggleHeading({ level: 3 }) },
    null,
    { label: 'Bulleted list', active: (e) => e.isActive('bulletList'), run: (c) => c.toggleBulletList() },
    { label: 'Numbered list', active: (e) => e.isActive('orderedList'), run: (c) => c.toggleOrderedList() },
    { label: 'Quote or callout', active: (e) => e.isActive('blockquote'), run: (c) => c.toggleBlockquote() },
    { label: 'Strikethrough', active: (e) => e.isActive('strike'), run: (c) => c.toggleStrike() },
    null,
    { label: 'Add link…', active: (e) => e.isActive('link'), run: (c, e) => {
        const current = e.getAttributes('link').href || 'https://';
        const url = window.prompt('Link address', current);
        if (url === null) return null;
        if (url.trim() === '' || url.trim() === 'https://') return c.extendMarkRange('link').unsetLink();
        return c.extendMarkRange('link').setLink({ href: url.trim() });
    } },
    { label: 'Remove link', when: (e) => e.isActive('link'), run: (c) => c.extendMarkRange('link').unsetLink() },
    { label: 'Image from a link…', run: (c) => {
        const src = window.prompt('Image address (https://…)', 'https://');
        if (!src || src.trim() === 'https://') return null;
        return c.setImage({ src: src.trim(), alt: window.prompt('Describe the image for screen readers', '') || '' });
    } },
    { label: 'Divider line', run: (c) => c.setHorizontalRule() },
    null,
    { label: 'Clear formatting', run: (c) => c.unsetAllMarks().clearNodes() },
];

function mount(textarea) {
    if (textarea.dataset.richMounted) return;
    textarea.dataset.richMounted = '1';

    const wrap = document.createElement('div');
    wrap.className = 'rich-editor';
    const bar = document.createElement('div');
    bar.className = 'rich-toolbar';
    bar.setAttribute('role', 'toolbar');
    bar.setAttribute('aria-label', 'Formatting');
    const area = document.createElement('div');
    area.className = `rich-area ${textarea.dataset.rich === 'compact' ? 'rich-compact' : 'prose-lesson'}`;
    wrap.append(bar, area);
    textarea.after(wrap);
    textarea.hidden = true;
    textarea.required = false; // a hidden required field blocks submit silently; the server validates

    const label = textarea.id ? document.querySelector(`label[for="${textarea.id}"]`) : null;
    const editor = new Editor({
        element: area,
        content: textarea.value,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                code: false,
                codeBlock: false,
                link: { openOnClick: false, autolink: true, defaultProtocol: 'https', HTMLAttributes: { rel: 'noopener noreferrer', target: null } },
            }),
            Image,
            TableKit,
        ],
        editorProps: { attributes: { class: 'rich-content', role: 'textbox', 'aria-multiline': 'true', ...(label ? { 'aria-label': label.textContent.trim() } : {}) } },
        onUpdate: ({ editor: e }) => {
            textarea.value = e.isEmpty ? '' : e.getHTML();
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        },
    });
    if (label) label.addEventListener('click', () => editor.commands.focus());

    const buttons = BUTTONS.map((b) => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'rich-btn';
        el.innerHTML = b.html;
        el.title = b.label;
        el.setAttribute('aria-label', b.label);
        el.addEventListener('mousedown', (ev) => ev.preventDefault()); // keep the selection
        el.addEventListener('click', () => b.run(editor.chain().focus()).run());
        bar.append(el);
        return [b, el];
    });

    // The ⌄ menu
    const more = document.createElement('div');
    more.className = 'relative';
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'rich-btn';
    toggle.title = 'More formatting';
    toggle.setAttribute('aria-label', 'More formatting');
    toggle.setAttribute('aria-haspopup', 'menu');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.innerHTML = icon('<path d="M5.5 8l4.5 4.5L14.5 8"/>');
    const menu = document.createElement('div');
    menu.className = 'rich-menu';
    menu.setAttribute('role', 'menu');
    menu.hidden = true;
    more.append(toggle, menu);
    bar.append(more);

    const closeMenu = () => { menu.hidden = true; toggle.setAttribute('aria-expanded', 'false'); };
    const openMenu = () => {
        menu.innerHTML = '';
        MENU.forEach((item) => {
            if (item === null) { const hr = document.createElement('div'); hr.className = 'rich-menu-sep'; menu.append(hr); return; }
            if (item.when && !item.when(editor)) return;
            const el = document.createElement('button');
            el.type = 'button';
            el.setAttribute('role', 'menuitem');
            el.className = 'rich-menu-item' + (item.active && item.active(editor) ? ' is-active' : '');
            el.textContent = item.label;
            el.addEventListener('mousedown', (ev) => ev.preventDefault());
            el.addEventListener('click', () => {
                closeMenu();
                const chain = item.run(editor.chain().focus(), editor);
                if (chain) chain.run();
            });
            menu.append(el);
        });
        menu.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        menu.querySelector('button')?.focus();
    };
    toggle.addEventListener('mousedown', (ev) => ev.preventDefault());
    toggle.addEventListener('click', () => (menu.hidden ? openMenu() : closeMenu()));
    document.addEventListener('click', (ev) => { if (!more.contains(ev.target)) closeMenu(); });
    menu.addEventListener('keydown', (ev) => {
        const items = [...menu.querySelectorAll('button')];
        const i = items.indexOf(document.activeElement);
        if (ev.key === 'Escape') { closeMenu(); toggle.focus(); }
        if (ev.key === 'ArrowDown') { ev.preventDefault(); items[(i + 1) % items.length]?.focus(); }
        if (ev.key === 'ArrowUp') { ev.preventDefault(); items[(i - 1 + items.length) % items.length]?.focus(); }
    });

    const refresh = () => buttons.forEach(([b, el]) => {
        const on = editor.isActive(b.name);
        el.classList.toggle('is-active', on);
        el.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    editor.on('selectionUpdate', refresh);
    editor.on('transaction', refresh);
    editor.on('focus', () => wrap.classList.add('is-focused'));
    editor.on('blur', () => wrap.classList.remove('is-focused'));
    refresh();
}

// Editors inside closed <details> (e.g. 250 questions in a bank) are created when opened.
export function initRichEditors(root = document) {
    root.querySelectorAll('textarea[data-rich]').forEach((ta) => {
        const details = ta.closest('details');
        if (details && !details.open) {
            details.addEventListener('toggle', () => details.open && mount(ta), { once: true });
        } else {
            mount(ta);
        }
    });
}
