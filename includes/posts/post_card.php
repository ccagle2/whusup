<?php

if (!function_exists('renderPostCards')) {
function renderPostCards($recent_posts, $comments_by_post, $post_images_by_post, $user_id = null, $is_logged_in = false) {
    ob_start();
    ?>

    <style>
    .recent-post-expand-button {
        display: none;
        margin: 0.45rem 0 0;
        padding: 0;
        border: 0;
        background: transparent;
        color: #4b5563;
        font: inherit;
        font-size: 0.86rem;
        font-weight: 700;
        line-height: 1.4;
        cursor: pointer;
        text-align: left;
    }

    .recent-post-expand-button:focus {
        outline: none;
        text-decoration: none;
    }

    .recent-post-expand-button:focus-visible {
        color: #111827;
        outline: 2px solid currentColor;
        outline-offset: 3px;
        border-radius: 4px;
        text-decoration: none;
    }

    @media (hover: hover) and (pointer: fine) {
        .recent-post-expand-button:hover {
            color: #111827;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
    }


    /*
     * The author control inherits the exact typography and appearance of the
     * existing author name. It behaves like a button without looking like a link.
     */
    .recent-post-author-button {
        appearance: none;
        -webkit-appearance: none;
        display: inline;
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: 0;
        background: transparent;
        color: inherit;
        font: inherit;
        font-weight: inherit;
        line-height: inherit;
        letter-spacing: inherit;
        text-align: inherit;
        text-decoration: none;
        cursor: pointer;
    }

    .recent-post-author-button:hover,
    .recent-post-author-button:active,
    .recent-post-author-button:focus {
        color: inherit;
        background: transparent;
        text-decoration: none;
        box-shadow: none;
    }

    .recent-post-author-button:focus {
        outline: none;
    }

    .recent-post-author-button:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 3px;
        border-radius: 4px;
    }

    .whusup-bio-modal {
        position: fixed;
        inset: 0;
        z-index: 1085;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .whusup-bio-modal.is-open {
        display: flex;
    }

    .whusup-bio-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.56);
        -webkit-backdrop-filter: blur(4px);
        backdrop-filter: blur(4px);
    }

    .whusup-bio-modal-dialog {
        position: relative;
        z-index: 1;
        width: min(100%, 500px);
        max-height: min(78vh, 620px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #ffffff;
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 22px;
        box-shadow:
            0 24px 70px rgba(15, 23, 42, 0.3),
            0 6px 18px rgba(15, 23, 42, 0.12);
        transform: translateY(8px) scale(0.985);
        opacity: 0;
        transition:
            transform 160ms ease,
            opacity 160ms ease;
    }

    .whusup-bio-modal.is-open .whusup-bio-modal-dialog {
        transform: translateY(0) scale(1);
        opacity: 1;
    }

    .whusup-bio-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.15rem 1.2rem 0.95rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.2);
    }

    .whusup-bio-modal-heading {
        min-width: 0;
    }

    .whusup-bio-modal-label {
        display: block;
        margin-bottom: 0.15rem;
        color: #6b7280;
        font-size: 0.74rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        line-height: 1.2;
        text-transform: uppercase;
    }

    .whusup-bio-modal-title {
        margin: 0;
        color: #111827;
        font-size: 1.2rem;
        font-weight: 800;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .whusup-bio-modal-close {
        appearance: none;
        -webkit-appearance: none;
        flex: 0 0 auto;
        width: 2.35rem;
        height: 2.35rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: -0.2rem -0.25rem 0 0;
        padding: 0;
        border: 0;
        border-radius: 999px;
        background: #f3f4f6;
        color: #374151;
        font-size: 1.6rem;
        font-weight: 400;
        line-height: 1;
        cursor: pointer;
        transition:
            background-color 140ms ease,
            transform 140ms ease;
    }

    .whusup-bio-modal-close:hover {
        background: #e5e7eb;
    }

    .whusup-bio-modal-close:active {
        transform: scale(0.94);
    }

    .whusup-bio-modal-close:focus {
        outline: none;
    }

    .whusup-bio-modal-close:focus-visible {
        outline: 3px solid rgba(37, 99, 235, 0.35);
        outline-offset: 2px;
    }

    .whusup-bio-modal-body {
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 1.2rem;
        -webkit-overflow-scrolling: touch;
    }

    .whusup-bio-modal-text {
        margin: 0;
        color: #374151;
        font-size: 1rem;
        line-height: 1.68;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .whusup-bio-modal-text.is-empty {
        color: #6b7280;
        font-style: italic;
    }

    body.whusup-bio-modal-open {
        overflow: hidden;
    }

    @media (max-width: 700px) {
        .whusup-bio-modal {
            align-items: flex-end;
            padding: 0;
        }

        .whusup-bio-modal-dialog {
            width: 100%;
            max-height: min(72vh, 560px);
            border-right: 0;
            border-bottom: 0;
            border-left: 0;
            border-radius: 24px 24px 0 0;
            transform: translateY(24px);
        }

        .whusup-bio-modal-header {
            padding:
                calc(1rem + env(safe-area-inset-top, 0px))
                1.1rem
                0.9rem;
        }

        .whusup-bio-modal-body {
            padding:
                1.05rem
                1.1rem
                calc(1.25rem + env(safe-area-inset-bottom, 0px));
        }

        .whusup-bio-modal-close {
            width: 2.55rem;
            height: 2.55rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .whusup-bio-modal-dialog,
        .whusup-bio-modal-close {
            transition: none;
        }
    }

    /*
     * Collapse longer post text to seven lines on both desktop and mobile.
     * The existing Show more / Show less JavaScript handles expansion.
     */
    .recent-post-text-wrapper.is-collapsible .recent-post-body {
        display: -webkit-box;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 7;
        line-clamp: 7;
    }

    .recent-post-text-wrapper.is-collapsible .recent-post-expand-button {
        display: inline-block;
    }

    .recent-post-text-wrapper.is-collapsible.is-expanded .recent-post-body {
        display: block !important;
        overflow: visible !important;
        max-height: none !important;
        height: auto !important;
        -webkit-box-orient: initial !important;
        -webkit-line-clamp: unset !important;
        line-clamp: unset !important;
    }


    .post-share-button {
        appearance: none;
        -webkit-appearance: none;
        display: inline-flex;
        align-items: center;
        gap: 0.32rem;
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
        color: inherit;
        font: inherit;
        line-height: 1;
        cursor: pointer;
    }

    .post-share-button:hover,
    .post-share-button:active {
        color: inherit;
        background: transparent;
    }

    .post-share-button:focus {
        outline: none;
    }

    .post-share-button:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 3px;
        border-radius: 4px;
    }

    .post-share-icon {
        width: 1.15rem;
        height: 1.15rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }

    .post-share-icon svg {
        width: 100%;
        height: 100%;
        display: block;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.9;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .post-share-label {
        font-size: 0.9rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .post-view-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-left: auto;
        color: #111827;
        font-size: 0.9rem;
        font-weight: 700;
        line-height: 1;
        pointer-events: none;
        user-select: none;
    }

    .post-view-icon {
        width: 1.45rem;
        height: 1.45rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .post-view-icon svg {
        display: block;
        width: 100%;
        height: 100%;
    }

    .post-view-count {
        white-space: nowrap;
    }

    /*
     * Keep Like, Comment, Views, and Share on one footer row.
     * Matching auto margins center Views between Comment and Share.
     */
    .recent-post-card.whusup-profile-left .recent-post-actions {
        display: flex;
        align-items: center;
        width: 100%;
    }

    .recent-post-card.whusup-profile-left .post-share-button {
        margin-left: auto;
    }


    /*
     * Move only the post profile emblem from the upper-right to the
     * upper-left while preserving the existing author/date spacing.
     */
    .recent-post-card.whusup-profile-left .profile-emblem {
        top: 18px;
        right: auto;
        left: 18px;
    }

    .recent-post-card.whusup-profile-left .recent-post-author,
    .recent-post-card.whusup-profile-left .recent-post-date {
        padding-right: clamp(120px, 36%, 230px);
        padding-left: 76px;
    }

    /*
     * Place the post tag in the space previously occupied by the profile
     * emblem. Its width is capped so long tags wrap without overlapping
     * the profile, author name, or timestamp.
     */
    .recent-post-card.whusup-profile-left .whusup-upper-right-tag {
        position: absolute;
        top: 22px;
        right: 18px;
        z-index: 2;
        width: max-content;
        max-width: min(42%, 260px);
        margin: 0;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: normal;
        text-align: right;
        line-height: 1.25;
    }

    @media (max-width: 700px) {
        .recent-post-card.whusup-profile-left .profile-emblem {
            top: 18px;
            right: auto;
            left: 18px;
        }

        .recent-post-card.whusup-profile-left .recent-post-author,
        .recent-post-card.whusup-profile-left .recent-post-date {
            padding-right: clamp(92px, 34%, 150px);
            padding-left: 62px;
        }

        .recent-post-card.whusup-profile-left .whusup-upper-right-tag {
            top: 22px;
            right: 18px;
            max-width: min(34%, 150px);
        }
    }

    </style>

    <script>
    (function () {
        if (window.whusupMobilePostExpanderInitialized) {
            return;
        }

        window.whusupMobilePostExpanderInitialized = true;
        window.whusupExpandedPostIds = window.whusupExpandedPostIds || new Set();

        let expanderActivatedByPointer = false;

        document.addEventListener('pointerdown', function (event) {
            expanderActivatedByPointer = Boolean(
                event.target.closest('.recent-post-expand-button')
            );
        });

        document.addEventListener('keydown', function (event) {
            if (
                (event.key === 'Enter' || event.key === ' ') &&
                event.target.closest('.recent-post-expand-button')
            ) {
                expanderActivatedByPointer = false;
            }
        });

        function getPostId(wrapper) {
            const card = wrapper.closest('.recent-post-card');
            return card ? String(card.dataset.postId || '') : '';
        }

        function applySavedState(root) {
            const searchRoot = root instanceof Element || root instanceof Document
                ? root
                : document;

            const wrappers = [];

            if (
                searchRoot instanceof Element &&
                searchRoot.matches('.recent-post-text-wrapper.is-collapsible')
            ) {
                wrappers.push(searchRoot);
            }

            searchRoot
                .querySelectorAll('.recent-post-text-wrapper.is-collapsible')
                .forEach(function (wrapper) {
                    wrappers.push(wrapper);
                });

            wrappers.forEach(function (wrapper) {
                const postId = getPostId(wrapper);
                const button = wrapper.querySelector('.recent-post-expand-button');
                const expanded = postId !== '' && window.whusupExpandedPostIds.has(postId);

                wrapper.classList.toggle('is-expanded', expanded);

                if (button) {
                    button.textContent = expanded ? 'Show less' : 'Show more';
                    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                }
            });
        }

        document.addEventListener('click', function (event) {
            const button = event.target.closest('.recent-post-expand-button');

            if (!button) {
                return;
            }

            const wrapper = button.closest('.recent-post-text-wrapper.is-collapsible');

            if (!wrapper) {
                return;
            }

            event.preventDefault();

            const postId = getPostId(wrapper);
            const expanding = !wrapper.classList.contains('is-expanded');

            wrapper.classList.toggle('is-expanded', expanding);
            button.textContent = expanding ? 'Show less' : 'Show more';
            button.setAttribute('aria-expanded', expanding ? 'true' : 'false');

            if (postId !== '') {
                if (expanding) {
                    window.whusupExpandedPostIds.add(postId);
                } else {
                    window.whusupExpandedPostIds.delete(postId);
                }
            }

            /*
             * Mouse and touch controls should not retain a visual focus state
             * after activation. Keyboard activation keeps focus so the control
             * remains accessible and easy to operate repeatedly.
             */
            if (expanderActivatedByPointer) {
                button.blur();
            }

            expanderActivatedByPointer = false;
        });

        function startPostExpander() {
            applySavedState(document);

            const observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node instanceof Element) {
                            applySavedState(node);
                        }
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startPostExpander, { once: true });
        } else {
            startPostExpander();
        }
    })();

    (function () {
        if (window.whusupUserBioModalInitialized) {
            return;
        }

        window.whusupUserBioModalInitialized = true;

        let modalElement = null;
        let modalTitle = null;
        let modalText = null;
        let closeButton = null;
        let lastTrigger = null;

        function createModal() {
            if (modalElement) {
                return modalElement;
            }

            modalElement = document.createElement('div');
            modalElement.className = 'whusup-bio-modal';
            modalElement.id = 'whusup-bio-modal';
            modalElement.setAttribute('role', 'dialog');
            modalElement.setAttribute('aria-modal', 'true');
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.setAttribute('aria-labelledby', 'whusup-bio-modal-title');

            modalElement.innerHTML = `
                <div class="whusup-bio-modal-backdrop" data-bio-modal-close></div>

                <section class="whusup-bio-modal-dialog" role="document">
                    <header class="whusup-bio-modal-header">
                        <div class="whusup-bio-modal-heading">
                            <span class="whusup-bio-modal-label">About</span>
                            <h2
                                class="whusup-bio-modal-title"
                                id="whusup-bio-modal-title"
                            ></h2>
                        </div>

                        <button
                            type="button"
                            class="whusup-bio-modal-close"
                            data-bio-modal-close
                            aria-label="Close bio"
                        >
                            &times;
                        </button>
                    </header>

                    <div class="whusup-bio-modal-body">
                        <p
                            class="whusup-bio-modal-text"
                            id="whusup-bio-modal-text"
                        ></p>
                    </div>
                </section>
            `;

            document.body.appendChild(modalElement);

            modalTitle = modalElement.querySelector('#whusup-bio-modal-title');
            modalText = modalElement.querySelector('#whusup-bio-modal-text');
            closeButton = modalElement.querySelector('.whusup-bio-modal-close');

            return modalElement;
        }

        function openModal(trigger) {
            createModal();

            const authorName = String(
                trigger.dataset.authorName || 'User'
            ).trim();

            const authorBio = String(
                trigger.dataset.authorBio || ''
            ).trim();

            lastTrigger = trigger;
            modalTitle.textContent = authorName || 'User';

            if (authorBio !== '') {
                modalText.textContent = authorBio;
                modalText.classList.remove('is-empty');
            } else {
                modalText.textContent = 'No bio is available.';
                modalText.classList.add('is-empty');
            }

            modalElement.setAttribute('aria-hidden', 'false');
            modalElement.classList.add('is-open');
            document.body.classList.add('whusup-bio-modal-open');

            window.requestAnimationFrame(function () {
                closeButton.focus({ preventScroll: true });
            });
        }

        function closeModal() {
            if (!modalElement || !modalElement.classList.contains('is-open')) {
                return;
            }

            modalElement.classList.remove('is-open');
            modalElement.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('whusup-bio-modal-open');

            if (lastTrigger && document.contains(lastTrigger)) {
                lastTrigger.focus({ preventScroll: true });
            }

            lastTrigger = null;
        }

        document.addEventListener('click', function (event) {
            const authorButton = event.target.closest(
                '.recent-post-author-button'
            );

            if (authorButton) {
                event.preventDefault();
                openModal(authorButton);
                return;
            }

            if (event.target.closest('[data-bio-modal-close]')) {
                event.preventDefault();
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (
                event.key === 'Escape' &&
                modalElement &&
                modalElement.classList.contains('is-open')
            ) {
                event.preventDefault();
                closeModal();
                return;
            }

            if (
                event.key !== 'Tab' ||
                !modalElement ||
                !modalElement.classList.contains('is-open')
            ) {
                return;
            }

            const focusable = Array.from(
                modalElement.querySelectorAll(
                    'button:not([disabled]), [href], input:not([disabled]), ' +
                    'select:not([disabled]), textarea:not([disabled]), ' +
                    '[tabindex]:not([tabindex="-1"])'
                )
            ).filter(function (element) {
                return element.offsetParent !== null;
            });

            if (focusable.length === 0) {
                event.preventDefault();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    })();

    (function () {
        if (window.whusupPostShareInitialized) {
            return;
        }

        window.whusupPostShareInitialized = true;

        function copyTextFallback(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            let copied = false;

            try {
                copied = document.execCommand('copy');
            } catch (error) {
                copied = false;
            }

            textarea.remove();
            return copied;
        }

        async function copyShareUrl(url) {
            if (
                navigator.clipboard &&
                typeof navigator.clipboard.writeText === 'function' &&
                window.isSecureContext
            ) {
                await navigator.clipboard.writeText(url);
                return true;
            }

            return copyTextFallback(url);
        }

        function temporarilyUpdateLabel(button, message) {
            const label = button.querySelector('.post-share-label');

            if (!label) {
                return;
            }

            const originalLabel = label.dataset.originalLabel || label.textContent;
            label.dataset.originalLabel = originalLabel;
            label.textContent = message;

            window.clearTimeout(button._whusupShareLabelTimer);
            button._whusupShareLabelTimer = window.setTimeout(function () {
                label.textContent = originalLabel;
            }, 1800);
        }

        document.addEventListener('click', async function (event) {
            const button = event.target.closest('.post-share-button');

            if (!button) {
                return;
            }

            event.preventDefault();

            const sharePath = String(button.dataset.sharePath || '').trim();
            const shareTitle = String(button.dataset.shareTitle || 'Whusup post').trim();

            if (sharePath === '') {
                return;
            }

            const shareUrl = new URL(sharePath, window.location.origin).href;

            try {
                if (typeof navigator.share === 'function') {
                    await navigator.share({
                        title: shareTitle,
                        url: shareUrl
                    });
                    return;
                }

                const copied = await copyShareUrl(shareUrl);
                temporarilyUpdateLabel(button, copied ? 'Copied!' : 'Copy failed');
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }

                try {
                    const copied = await copyShareUrl(shareUrl);
                    temporarilyUpdateLabel(button, copied ? 'Copied!' : 'Copy failed');
                } catch (copyError) {
                    temporarilyUpdateLabel(button, 'Copy failed');
                }
            }
        });
    })();
    </script>

    <?php

    foreach ($recent_posts as $post) {
        $render_single_post_card = true;
        include __FILE__;
    }

    return ob_get_clean();
}
}

if (empty($render_single_post_card)) {
    return;
}
?>
            <?php
                $post_id = (int) $post['id'];
                $comment_count = (int) $post['comment_count'];
                $view_count = max(0, (int) ($post['view_count'] ?? 0));
                $is_owner = $is_logged_in && $post['user_id'] === $user_id;

                $postShareSlug = makePostSlug(
                    !empty($post['tag']) ? $post['tag'] : $post['body']
                );
                $postSharePath = '/post/' . rawurlencode((string) $post_id) . '/' . rawurlencode($postShareSlug);
                $postShareTitleSource = !empty($post['tag'])
                    ? trim((string) $post['tag'])
                    : trim((string) ($post['body'] ?? ''));
                $postShareTitle = function_exists('mb_substr')
                    ? mb_substr($postShareTitleSource, 0, 80, 'UTF-8')
                    : substr($postShareTitleSource, 0, 80);

                if ($postShareTitle === '') {
                    $postShareTitle = 'Whusup post';
                }

                $authorName = trim((string) ($post['name'] ?? 'User'));
                $authorDisplayName = ucwords($authorName);
                /*
                 * The feed query should expose user_profiles.bio and
                 * user_profiles.is_private. A bio is shown only when the
                 * profile is explicitly public (is_private = 0).
                 */
                $profileIsPublic = isset($post['is_private'])
                    && (int) $post['is_private'] === 0;

                $authorBio = $profileIsPublic
                    ? trim((string) ($post['bio'] ?? ''))
                    : '';

                $name_parts = explode(' ', $authorName);
                $first_initial = strtoupper(substr($name_parts[0], 0, 1));
                $last_initial = '';

                if (count($name_parts) > 1) {
                    $last_initial = strtoupper(substr(end($name_parts), 0, 1));
                }

                $first_letter = $first_initial . $last_initial;

                $profileImageUrl = !empty($post['profile_picture_url'])
                    ? getS3ImageUrl($post['profile_picture_url'])
                    : null;


                $postBodyText = trim((string) ($post['body'] ?? ''));
                $postBodyLength = function_exists('mb_strlen')
                    ? mb_strlen($postBodyText, 'UTF-8')
                    : strlen($postBodyText);
                $postBodyLineCount = substr_count($postBodyText, "\n") + 1;

                // Render the expander for posts that are likely to exceed seven displayed lines.
                $isLongPostBody = $postBodyLength > 320 || $postBodyLineCount > 7;
            ?>

            <div class="recent-post-card whusup-profile-left" data-post-id="<?= htmlspecialchars($post_id) ?>">

                <div class="profile-emblem">
                    <?php if (!empty($profileImageUrl)): ?>
                        <img
                            src="<?= htmlspecialchars($profileImageUrl) ?>"
                            alt="Profile picture"
                            class="profile-emblem-image profile-expandable"
                            data-full-image="<?= htmlspecialchars($profileImageUrl) ?>"
                            loading="eager"
                        >
                    <?php else: ?>
                        <?= htmlspecialchars($first_letter) ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($post['tag'])): ?>
                    <a
                        href="/tag/<?= rawurlencode(makePostSlug($post['tag'])) ?>"
                        class="post-tag whusup-upper-right-tag"
                        title="View posts tagged <?= htmlspecialchars($post['tag']) ?>"
                    >
                        <?= htmlspecialchars($post['tag']) ?>
                    </a>
                <?php endif; ?>

                <div class="recent-post-author">
                    <button
                        type="button"
                        class="recent-post-author-button"
                        data-author-name="<?= htmlspecialchars($authorDisplayName, ENT_QUOTES, 'UTF-8') ?>"
                        data-author-bio="<?= htmlspecialchars($authorBio, ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="View <?= htmlspecialchars($authorDisplayName, ENT_QUOTES, 'UTF-8') ?>'s bio"
                    >
                        <?= htmlspecialchars($authorDisplayName) ?>
                    </button>
                </div>

                <div class="recent-post-date">
                    <a
                        href="<?= htmlspecialchars($postSharePath, ENT_QUOTES, 'UTF-8') ?>"
                        class="post-permalink"
                        title="View this post"
                    >
                        <?= htmlspecialchars(timeAgo($post['created_at'])) ?>
                    </a>
                </div>

                <div class="recent-post-text-wrapper<?= $isLongPostBody ? ' is-collapsible' : '' ?>">
                    <div
                        class="recent-post-body"
                        id="post-body-<?= htmlspecialchars($post_id) ?>"
                    >
                        <?= linkifyText($post['body']) ?>
                    </div>

                    <?php if ($isLongPostBody): ?>
                        <button
                            type="button"
                            class="recent-post-expand-button"
                            aria-controls="post-body-<?= htmlspecialchars($post_id) ?>"
                            aria-expanded="false"
                        >
                            Show more
                        </button>
                    <?php endif; ?>
                </div>

                <?php
                    $postImageKeys = $post_images_by_post[$post_id] ?? [];
                    $postImageKeys = array_slice(array_values(array_filter($postImageKeys)), 0, 3);
                ?>

                <?php if (!empty($postImageKeys)): ?>
                    <div
                        class="whusup-post-gallery <?= count($postImageKeys) > 1 ? 'has-multiple-images' : 'has-single-image' ?>"
                        data-post-id="<?= htmlspecialchars($post_id) ?>"
                        data-current-index="0"
                    >
                        <div class="whusup-gallery-stage">
                            <div class="whusup-gallery-track">
                                <?php foreach ($postImageKeys as $imageIndex => $postImageKey): ?>
                                    <?php $postImageUrl = getS3ImageUrl($postImageKey); ?>

                                    <?php if (!empty($postImageUrl)): ?>
                                        <div class="whusup-gallery-slide">
                                            <div class="whusup-gallery-frame">
                                                <img
                                                    src="<?= htmlspecialchars($postImageUrl) ?>"
                                                    alt="Post image <?= htmlspecialchars($imageIndex + 1) ?>"
                                                    class="whusup-gallery-image"
                                                    data-post-id="<?= htmlspecialchars($post_id) ?>"
                                                    data-image-index="<?= htmlspecialchars($imageIndex) ?>"
                                                    data-full-image="<?= htmlspecialchars($postImageUrl) ?>"
                                                    loading="eager"
                                                    decoding="async"
                                                >
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>

                            <?php if (count($postImageKeys) > 1): ?>
                                <button
                                    type="button"
                                    class="post-image-carousel-arrow post-image-carousel-prev"
                                    aria-label="Previous image"
                                >
                                    ‹
                                </button>

                                <button
                                    type="button"
                                    class="post-image-carousel-arrow post-image-carousel-next"
                                    aria-label="Next image"
                                >
                                    ›
                                </button>

                                <div class="post-image-carousel-dots" aria-label="Image position">
                                    <?php foreach ($postImageKeys as $dotIndex => $unusedImageKey): ?>
                                        <button
                                            type="button"
                                            class="post-image-carousel-dot <?= $dotIndex === 0 ? 'active' : '' ?>"
                                            data-dot-index="<?= htmlspecialchars($dotIndex) ?>"
                                            aria-label="Show image <?= htmlspecialchars($dotIndex + 1) ?>"
                                        ></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($is_owner): ?>
                    <div class="post-owner-actions">
                        <button 
                            type="button" 
                            class="comment-action-link comment-edit-link edit-toggle-button"
                            data-target="edit-post-<?= htmlspecialchars($post_id) ?>"
                        >
                            Edit
                        </button>

                        <form
                            method="POST"
                            action=""
                            class="post-delete-form"
                        >
                            <input type="hidden" name="delete_post_id" value="<?= htmlspecialchars($post_id) ?>">

                            <button 
                                type="submit" 
                                class="comment-action-link comment-delete-link"
                                onclick="return confirm('Are you sure you want to delete this post?');"
                            >
                                Delete
                            </button>
                        </form>
                    </div>

                    <div id="edit-post-<?= htmlspecialchars($post_id) ?>" class="edit-post-section">
                        <form class="edit-post-form ajax-edit-post-form">
                            <input type="hidden" name="post_id" value="<?= htmlspecialchars($post_id) ?>">

                            <textarea name="post_body"><?= htmlspecialchars($post['body']) ?></textarea>

                            <button type="submit" class="recent-post-button">
                                Save Post
                            </button>

                            <div class="ajax-comment-message"></div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="recent-post-footer">
                    <div class="recent-post-actions">

                        <span
                            class="like-indicator ajax-like-indicator <?= $post['user_liked'] ? 'liked' : '' ?> <?= $is_logged_in ? 'can-like' : 'cannot-like' ?>"
                            data-post-id="<?= htmlspecialchars($post_id) ?>"
                            data-logged-in="<?= $is_logged_in ? '1' : '0' ?>"
                        >
                            <span class="like-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M7 10v10H4V10h3zm3 10h7.4c.9 0 1.7-.6 1.9-1.5l1.4-6.2c.3-1.2-.6-2.3-1.9-2.3h-5.1l.8-3.8c.1-.6-.1-1.2-.5-1.6L13.2 4 9 9.1V20z"/>
                                </svg>
                            </span>

                            <span class="like-count">
                                <?= htmlspecialchars($post['like_count']) ?>
                            </span>
                        </span>

                        <span class="comment-icon-toggle" data-target="comments-<?= htmlspecialchars($post_id) ?>" title="Show comments">
                            <span class="comment-icon">💬</span>
                            <span class="comment-count"><?= htmlspecialchars($comment_count) ?></span>
                        </span>

                        <span
                            class="post-view-indicator"
                            title="<?= htmlspecialchars(number_format($view_count)) ?> views"
                            aria-label="<?= htmlspecialchars(number_format($view_count)) ?> views"
                        >
                            <span class="post-view-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <!-- Upper eyelid -->
                                    <path
                                        d="M2 12C4.2 8.3 7.8 6 12 6C16.2 6 19.8 8.3 22 12"
                                        fill="none"
                                        stroke="#111827"
                                        stroke-width="2.2"
                                        stroke-linecap="round"
                                    />
                            
                                    <!-- Lower eyelid -->
                                    <path
                                        d="M22 12C19.8 15.7 16.2 18 12 18C7.8 18 4.2 15.7 2 12"
                                        fill="none"
                                        stroke="#111827"
                                        stroke-width="2.2"
                                        stroke-linecap="round"
                                    />
                            
                                    <!-- Iris -->
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="3.4"
                                        fill="#2563EB"
                                    />
                            
                                    <!-- Pupil -->
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="1.45"
                                        fill="#111827"
                                    />
                            
                                    <!-- Reflection -->
                                    <circle
                                        cx="11.1"
                                        cy="10.9"
                                        r="0.55"
                                        fill="#FFFFFF"
                                    />
                            
                                    <!-- Upper eyelashes -->
                                    <path
                                        d="M7.2 7.6L6.5 6.7"
                                        stroke="#111827"
                                        stroke-width="1.4"
                                        stroke-linecap="round"
                                    />
                                    <path
                                        d="M12 6.6V5.4"
                                        stroke="#111827"
                                        stroke-width="1.4"
                                        stroke-linecap="round"
                                    />
                                    <path
                                        d="M16.8 7.6L17.5 6.7"
                                        stroke="#111827"
                                        stroke-width="1.4"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </span>

                            <span
                                class="post-view-count"
                                data-post-id="<?= htmlspecialchars($post_id) ?>"
                            >
                                <?= htmlspecialchars(number_format($view_count)) ?>
                            </span>
                        </span>

                        <button
                            type="button"
                            class="post-share-button"
                            data-share-path="<?= htmlspecialchars($postSharePath, ENT_QUOTES, 'UTF-8') ?>"
                            data-share-title="<?= htmlspecialchars($postShareTitle, ENT_QUOTES, 'UTF-8') ?>"
                            title="Share this post"
                            aria-label="Share this post"
                        >
                            <span class="post-share-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <circle cx="18" cy="5" r="3"></circle>
                                    <circle cx="6" cy="12" r="3"></circle>
                                    <circle cx="18" cy="19" r="3"></circle>
                                    <path d="M8.6 10.7 15.4 6.3"></path>
                                    <path d="m8.6 13.3 6.8 4.4"></path>
                                </svg>
                            </span>
                            <span class="post-share-label">Share</span>
                        </button>

                    </div>

                </div>

                <div id="comments-<?= htmlspecialchars($post_id) ?>" class="comments-section">

                    <p class="no-comments-text" style="<?= $comment_count === 0 ? '' : 'display:none;' ?>">
                        No comments yet.
                    </p>

                    <div class="comment-list">
                        <?php renderCommentTree($comments_by_post[$post_id] ?? [], null, $post_id, $user_id, $is_logged_in); ?>
                    </div>

                    <?php if ($is_logged_in): ?>
                        <form class="comment-form ajax-add-comment-form">
                            <input type="hidden" name="post_id" value="<?= htmlspecialchars($post_id) ?>">
                            <input type="hidden" name="parent_comment_id" value="">

                            <textarea name="comment_body" placeholder="Write a comment..."></textarea>

                            <button type="submit" class="recent-post-button">
                                Comment
                            </button>

                            <div class="ajax-comment-message"></div>
                        </form>
                    <?php else: ?>
                        <p class="login-message">
                            <a href="/login.php">Log in</a> to like or comment.
                        </p>
                    <?php endif; ?>

                </div>

            </div>
