// CFP main JS - Phase 4

document.addEventListener('DOMContentLoaded', () => {
    setupDonationAjax();
    setupCommentAjax();
});

function setupDonationAjax() {
    const form = document.querySelector('[data-cfp-donate-ajax="1"]');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = document.querySelector('#cfp-donate-message');
        if (msg) msg.textContent = 'Processing donation...';

        const formData = new FormData(form);
        formData.append('ajax', '1');

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (msg) {
                msg.textContent = data.message || (data.success ? 'Donation successful.' : 'Donation failed.');
                msg.className = data.success ? 'cfp-alert cfp-alert-success' : 'cfp-alert cfp-alert-error';
            }
        } catch (err) {
            if (msg) {
                msg.textContent = 'Network error submitting donation.';
                msg.className = 'cfp-alert cfp-alert-error';
            }
        }
    });
}

function setupCommentAjax() {
    const form = document.querySelector('[data-cfp-comment-ajax="1"]');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = document.querySelector('#cfp-comment-message');
        const list = document.querySelector('#cfp-comments-list');
        if (msg) {
            msg.textContent = 'Posting comment...';
            msg.className = 'cfp-alert cfp-alert-success';
        }

        const formData = new FormData(form);
        formData.append('ajax', '1');

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (!data.success) {
                if (msg) {
                    msg.textContent = data.error || 'Could not post comment.';
                    msg.className = 'cfp-alert cfp-alert-error';
                }
                return;
            }

            if (msg) {
                msg.textContent = 'Comment posted.';
                msg.className = 'cfp-alert cfp-alert-success';
            }

            if (list && data.comment_html) {
                const li = document.createElement('li');
                li.innerHTML = data.comment_html;
                list.prepend(li);
            }

            form.reset();
        } catch (err) {
            if (msg) {
                msg.textContent = 'Network error posting comment.';
                msg.className = 'cfp-alert cfp-alert-error';
            }
        }
    });
}


