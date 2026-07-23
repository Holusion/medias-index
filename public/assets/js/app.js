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
        const dialog = document.getElementById('embed-dialog');
        const codeElement = dialog?.querySelector('[data-embed-code]');
        const subjectElement = dialog?.querySelector('[data-embed-subject]');
        const previewBox = dialog?.querySelector('[data-embed-preview]');
        const previewToggle = dialog?.querySelector('[data-embed-preview-toggle]');

        /** What the dialog is currently showing, read back when the toggle moves. */
        let subject = null;

        /**
         * Built on demand and destroyed when hidden.
         *
         * Leaving the frame in place with the box merely hidden would keep the
         * content loading and playing — which for a virtual tour is tens of
         * megabytes fetched because someone glanced at a snippet.
         */
        function renderPreview(enabled) {
            if (!previewBox) {
                return;
            }

            previewBox.replaceChildren();
            previewBox.hidden = !enabled || subject === null;

            if (!enabled || subject === null) {
                return;
            }

            const frame = document.createElement('iframe');
            frame.src = subject.src;
            frame.title = subject.title;
            frame.setAttribute('allowfullscreen', '');
            // The real proportions of the snippet, scaled to the dialog.
            frame.style.aspectRatio = `${subject.width} / ${subject.height}`;
            previewBox.appendChild(frame);
        }

        function openDialog(trigger, withPreview) {
            subject = {
                src: trigger.dataset.embedSrc ?? '',
                title: trigger.dataset.embedTitle ?? '',
                width: Number(trigger.dataset.embedWidth) || 800,
                height: Number(trigger.dataset.embedHeight) || 600,
            };

            codeElement.textContent = trigger.dataset.embed;

            if (subjectElement) {
                subjectElement.textContent = subject.title;
            }

            if (previewToggle) {
                previewToggle.checked = withPreview;
            }

            renderPreview(withPreview);
            dialog.showModal();
        }

        // One delegated listener: the media list is re-rendered per page, and
        // this keeps working whatever it contains.
        document.addEventListener('click', (event) => {
            const copyButton = event.target.closest('[data-copy]');

            if (copyButton) {
                copyFromButton(copyButton, copyButton.dataset.copy);
                return;
            }

            const trigger = event.target.closest('[data-embed]');

            if (trigger && dialog && codeElement) {
                // A thumbnail carries data-preview: clicking a picture asks to
                // see the thing, not to read markup about it.
                openDialog(trigger, trigger.hasAttribute('data-preview'));
                return;
            }

            const dialogCopy = event.target.closest('[data-embed-copy]');

            if (dialogCopy && codeElement) {
                copyFromButton(dialogCopy, codeElement.textContent);
            }
        });

        previewToggle?.addEventListener('change', () => renderPreview(previewToggle.checked));

        // A scan runs synchronously, so the page simply hangs until it answers.
        // Say so, and stop a second submit from queueing another one behind it.
        document.querySelector('[data-scan-form]')?.addEventListener('submit', (event) => {
            const button = event.currentTarget.querySelector('button[type="submit"]');

            if (button) {
                button.dataset.feedback = 'Indexation…';
                // After the tick, so disabling cannot cancel this submission.
                window.setTimeout(() => { button.disabled = true; }, 0);
            }
        });

        // Escape, the close buttons and the backdrop all end here.
        dialog?.addEventListener('close', () => {
            subject = null;
            renderPreview(false);
        });

        // Selecting the snippet by hand should not mean dragging across it.
        codeElement?.parentElement?.addEventListener('click', (event) => {
            if (!event.target.closest('button')) {
                selectAll(codeElement);
            }
        });

        // Clicking outside the panel closes it: the backdrop is the dialog
        // element itself, so a click landing on it is a click on nothing else.
        dialog?.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });
})();
