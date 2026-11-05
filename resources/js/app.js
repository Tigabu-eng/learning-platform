import Sortable from 'sortablejs';
import { initRichEditors } from './rich-editor';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

async function postJSON(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body: JSON.stringify(body),
    });
    if (!res.ok) throw new Error('Request failed: ' + res.status);
    return res.json();
}

function toast(text) {
    let el = document.getElementById('toast');
    if (!el) return;
    el.textContent = text;
    el.classList.remove('opacity-0');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.add('opacity-0'), 1800);
}

/* ---------- Curriculum drag & drop (chapters and lessons) ---------- */
function initCurriculum() {
    const root = document.querySelector('[data-curriculum]');
    if (!root) return;
    const url = root.dataset.reorderUrl;
    const save = () => {
        const chapters = [...root.querySelectorAll(':scope > [data-chapter]')].map((ch) => ({
            id: Number(ch.dataset.chapter),
            lessons: [...ch.querySelectorAll('[data-lesson]')].map((l) => Number(l.dataset.lesson)),
        }));
        postJSON(url, { chapters }).then(() => toast('Order saved')).catch(() => toast('Couldn’t save the new order'));
    };
    Sortable.create(root, { handle: '[data-chapter-handle]', animation: 150, draggable: '[data-chapter]', onEnd: save });
    root.querySelectorAll('[data-lessons]').forEach((list) =>
        Sortable.create(list, { group: 'lessons', handle: '.drag-handle', animation: 150, onEnd: save })
    );
}

/* ---------- Question reorder in the quiz builder ---------- */
function initQuestionSort() {
    const list = document.querySelector('[data-questions]');
    if (!list) return;
    Sortable.create(list, {
        handle: '.drag-handle', animation: 150,
        onEnd: () => {
            const order = [...list.querySelectorAll('[data-question]')].map((q) => Number(q.dataset.question));
            postJSON(list.dataset.reorderUrl, { order }).then(() => toast('Order saved'));
        },
    });
}

/* ---------- Question editor: show the right fields per type ---------- */
function initQuestionForms() {
    document.querySelectorAll('[data-question-form]').forEach((form) => {
        const type = form.querySelector('[name="type"]');
        const sync = () => {
            const t = type.value;
            form.querySelectorAll('[data-show-for]').forEach((el) => {
                el.hidden = !el.dataset.showFor.split(' ').includes(t);
            });
            form.querySelectorAll('[data-correct]').forEach((input) => {
                input.type = t === 'multiple' ? 'checkbox' : 'radio';
            });
        };
        type.addEventListener('change', sync);
        sync();

        const list = form.querySelector('[data-option-list]');
        form.querySelector('[data-add-option]')?.addEventListener('click', () => {
            const tpl = list.querySelector('[data-option]');
            const n = Date.now();
            const row = tpl.cloneNode(true);
            row.querySelector('input[type=text]').value = '';
            row.querySelector('input[type=text]').name = `options[${n}][text]`;
            const c = row.querySelector('[data-correct]');
            c.value = String(n); c.checked = false;
            list.appendChild(row);
            row.querySelector('input[type=text]').focus();
        });
        list?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-remove-option]');
            if (btn && list.querySelectorAll('[data-option]').length > 2) btn.closest('[data-option]').remove();
        });
    });
}

/* ---------- Quiz taking: timer, autosave, progress ---------- */
function initQuiz() {
    const form = document.querySelector('[data-quiz-form]');
    if (!form) return;
    const timerEl = document.querySelector('[data-timer]');
    const answeredEl = document.querySelector('[data-answered]');
    let deadline = form.dataset.secondsLeft ? Date.now() + Number(form.dataset.secondsLeft) * 1000 : null;
    let submitting = false;

    const collect = () => {
        const answers = {};
        new FormData(form).forEach((v, k) => {
            const m = k.match(/^answers\[(\d+)\](\[\])?$/);
            if (!m) return;
            if (m[2]) (answers[m[1]] ??= []).push(v);
            else answers[m[1]] = v;
        });
        return answers;
    };
    const updateAnswered = () => {
        const a = collect();
        const count = Object.values(a).filter((v) => (Array.isArray(v) ? v.length : String(v).trim() !== '')).length;
        if (answeredEl) answeredEl.textContent = count;
        document.querySelectorAll('[data-qnav]').forEach((dot) => {
            const v = a[dot.dataset.qnav];
            const on = !!(Array.isArray(v) ? v.length : v && String(v).trim());
            dot.classList.toggle('bg-pine', on);
            dot.classList.toggle('border-pine', on);
            dot.classList.toggle('text-white', on);
            dot.classList.toggle('bg-white', !on);
        });
    };

    let saveTimer;
    let dirty = false;
    const save = () => postJSON(form.dataset.autosaveUrl, { answers: collect() }).then((r) => {
        dirty = false;
        if (r.seconds_left !== null && r.seconds_left !== undefined) deadline = Date.now() + r.seconds_left * 1000;
        toast('Answers saved');
    });
    form.addEventListener('input', () => {
        dirty = true;
        updateAnswered();
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => save().catch(() => {}), 700);
    });
    document.querySelector('[data-save-leave]')?.addEventListener('click', (e) => {
        e.preventDefault();
        const href = e.currentTarget.href;
        clearTimeout(saveTimer);
        save().catch(() => {}).finally(() => { dirty = false; window.location = href; });
    });
    form.addEventListener('submit', (e) => {
        if (submitting) return;
        const total = Number(form.dataset.total);
        const answered = Number(answeredEl?.textContent || 0);
        if (!form.dataset.force && answered < total && !confirm(`You’ve answered ${answered} of ${total} questions. Submit anyway?`)) {
            e.preventDefault(); return;
        }
        submitting = true;
        window.onbeforeunload = null;
    });
    window.onbeforeunload = () => (dirty && !submitting ? true : undefined);
    updateAnswered();

    if (deadline && timerEl) {
        const tick = () => {
            const left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
            const m = Math.floor(left / 60), s = left % 60;
            timerEl.textContent = `${m}:${String(s).padStart(2, '0')}`;
            timerEl.closest('[data-timer-box]')?.classList.toggle('text-brick', left <= 60);
            if (left <= 0) {
                form.dataset.force = '1';
                submitting = true; window.onbeforeunload = null;
                form.submit();
                return;
            }
            setTimeout(tick, 1000);
        };
        tick();
    }
}

/* ---------- Small helpers ---------- */
function initConfirm() {
    document.addEventListener('submit', (e) => {
        const msg = e.target.dataset?.confirm;
        if (msg && !confirm(msg)) e.preventDefault();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initRichEditors();
    initCurriculum();
    initQuestionSort();
    initQuestionForms();
    initQuiz();
    initConfirm();
});

// Studio: show one topic at a time in a long question bank.
document.querySelectorAll('[data-topic-filter]').forEach((select) => {
    select.addEventListener('change', () => {
        document.querySelectorAll('[data-questions] > [data-question]').forEach((li) => {
            li.hidden = select.value !== '*' && li.dataset.topic !== select.value;
        });
    });
});

// Menus built on <details data-menu>: close on outside tap, Escape, or choosing a link.
document.addEventListener('click', (e) => {
    document.querySelectorAll('details[data-menu][open]').forEach((d) => { if (!d.contains(e.target)) d.open = false; });
});
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('details[data-menu][open]').forEach((d) => { d.open = false; d.querySelector('summary')?.focus(); });
});
