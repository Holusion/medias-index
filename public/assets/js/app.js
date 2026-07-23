/**
 * The only script in the application.
 *
 * Everything it powers is an enhancement: the pages are complete without it, and
 * the controls it drives stay hidden until it has run (see the `has-js` class in
 * the layout), so no button is ever present but dead.
 */
(() => {
    'use strict';

    const FEEDBACK_MS = 1600;

    /**
     * The async Clipboard API needs a secure context and a permission that some
     * browsers refuse anyway, so the deprecated execCommand path stays as a
     * fallback rather than leaving the button silently doing nothing.
     */
    async function copyText(text) {
        if (navigator.clipboard) {
            try {
                await navigator.clipboard.writeText(text);
                return true;
            } catch {
                // Fall through: no secure context, permission refused, or no
                // user gesture credited.
            }
        }

        return fallbackCopy(text);
    }

    function fallbackCopy(text) {
        // An open modal <dialog> makes everything outside it inert, so a
        // textarea parked on <body> cannot take focus and the copy quietly does
        // nothing. It has to live inside whatever is on top.
        const host = document.querySelector('dialog[open]') ?? document.body;
        const previous = document.activeElement;

        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.top = '-1000px';
        field.style.opacity = '0';
        host.appendChild(field);
        field.focus();
        field.select();

        // execCommand answers true even when nothing was selected, so its word
        // is not enough: if the field never took focus, nothing was copied.
        let copied = document.activeElement === field
            && field.selectionEnd - field.selectionStart === text.length;

        if (copied) {
            try {
                copied = document.execCommand('copy');
            } catch {
                copied = false;
            }
        }

        field.remove();

        if (previous instanceof HTMLElement) {
            previous.focus();
        }

        return copied;
    }

    /**
     * Says what happened on the button itself, then clears the message.
     *
     * The label is never replaced — the stylesheet hides it and lays the message
     * over it, so the button keeps the width it had. Which is also why these
     * strings stay short: they have to fit over the shortest label they cover.
     */
    async function copyFromButton(button, text) {
        window.clearTimeout(Number(button.dataset.feedbackTimer));

        const copied = await copyText(text);

        button.dataset.feedback = copied ? 'Copié !' : 'Échec';
        button.classList.toggle('is-success', copied);
        button.classList.toggle('is-error', !copied);

        button.dataset.feedbackTimer = String(window.setTimeout(() => {
            delete button.dataset.feedback;
            delete button.dataset.feedbackTimer;
            button.classList.remove('is-success', 'is-error');
        }, FEEDBACK_MS));
    }

    function selectAll(element) {
        const range = document.createRange();
        range.selectNodeContents(element);

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Copying is the only thing here that needs a script; everything else on
        // the page is a link or a form and works without one.
        document.addEventListener('click', (event) => {
            const copyButton = event.target.closest('[data-copy]');

            if (copyButton) {
                copyFromButton(copyButton, copyButton.dataset.copy);
                return;
            }

            const codeButton = event.target.closest('[data-embed-copy]');
            const code = document.querySelector('[data-embed-code]');

            if (codeButton && code) {
                copyFromButton(codeButton, code.textContent);
            }
        });

        // Selecting the snippet by hand should not mean dragging across it.
        document.querySelector('.code-block')?.addEventListener('click', (event) => {
            const code = document.querySelector('[data-embed-code]');

            if (code && !event.target.closest('button')) {
                selectAll(code);
            }
        });

        // The preview loads only when asked for. The frame keeps its size, so
        // swapping the poster for the iframe moves nothing on the page.
        document.querySelector('[data-preview-start]')?.addEventListener('click', (event) => {
            const frame = event.currentTarget.closest('[data-preview]');

            if (!frame) {
                return;
            }

            const iframe = document.createElement('iframe');
            iframe.src = frame.dataset.previewSrc;
            iframe.title = frame.dataset.previewTitle ?? '';
            iframe.setAttribute('allowfullscreen', '');
            frame.replaceChildren(iframe);
        });

        // A scan runs synchronously, so the page simply hangs until it answers.
        // Say so, and stop a second submit from queueing another one behind it.
        document.querySelector('[data-scan-form]')?.addEventListener('submit', (event) => {
            const button = event.currentTarget.querySelector('button[type="submit"]');

            if (button) {
                button.dataset.feedback = 'Indexation\u2026';
                // After the tick, so disabling cannot cancel this submission.
                window.setTimeout(() => { button.disabled = true; }, 0);
            }
        });
    });
})();
