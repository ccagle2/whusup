<style>
/* Whusup recent posts styles — Phase 2 gallery cleanup. */
:root {
    --whusup-image-radius: 18px;
}

.recent-posts-wrapper {
    max-width: 900px;
    margin: 0 auto 28px;
    padding: 20px 20px 0;
    box-sizing: border-box;
}

.sort-button-row {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}

.sort-button {
    display: inline-block;
    background: #ffffff;
    color: #111827;
    border: 1px solid #111827;
    padding: 9px 24px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: 0.2s ease;
}

.sort-button:hover,
.sort-button.active {
    background: #111827;
    color: #ffffff;
}

.tag-filter-notice {
    max-width: 680px;
    margin: -8px auto 18px;
    padding: 10px 16px;
    border-radius: 999px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    color: #4b5563;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
}

.recent-post-card {
    position: relative;
    background: #ffffff;
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 18px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    text-align: left;
}

.profile-emblem {
    position: absolute;
    top: 18px;
    right: 18px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #e5e7eb;
    color: #4b5563;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 800;
    font-family: "Poppins", sans-serif;
    box-shadow: 0 4px 14px rgba(0,0,0,0.14);
    overflow: hidden;
    border: 3px solid #ffffff;
}

.profile-emblem-image {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    object-position: center;
}

.profile-expandable {
    cursor: zoom-in;
}

.blurred-name {
    filter: blur(5px);
    user-select: none;
    pointer-events: none;
}

.recent-post-author {
    font-weight: bold;
    font-size: 18px;
    margin-bottom: 2px;
    color: #111827;
    padding-right: 76px;
}

.recent-post-date {
    color: #666;
    font-size: 13px;
    margin-bottom: 14px;
    padding-right: 76px;
}

.recent-post-body {
    line-height: 1.6;
    color: #333;
    margin-bottom: 12px;
    text-align: left;
    word-break: break-word;
}


/*
    Whusup gallery system.
    This is the only image/card gallery implementation. The older
    recent-post-image-* gallery classes have been removed from post_card.php
    and should not be styled here.
*/
.whusup-post-gallery {
    width: 100%;
    margin: 10px 0 13px;
}

.whusup-gallery-stage {
    position: relative;
    width: 100%;
    overflow: hidden;
    touch-action: pan-y;
    background: transparent;
}

.whusup-gallery-track {
    display: flex;
    width: 100%;
    transition: transform 0.28s ease;
    will-change: transform;
}

.whusup-gallery-slide {
    flex: 0 0 100%;
    min-width: 100%;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    margin: 0;
    box-sizing: border-box;
    background: transparent;
    overflow: hidden;
}

.whusup-gallery-frame {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    max-width: 100%;
    overflow: hidden;
    border-radius: var(--whusup-image-radius);
    background: transparent;
    line-height: 0;
    -webkit-mask-image: -webkit-radial-gradient(white, black);
    mask-image: radial-gradient(white, black);
}

.whusup-gallery-image {
    display: block;
    width: auto;
    height: auto;
    max-width: 100%;
    border: 0;
    border-radius: 0;
    background: transparent;
    object-fit: contain;
    object-position: center;
    cursor: zoom-in;
    box-sizing: border-box;
}

.whusup-gallery-image.blurred-post-image {
    filter: blur(14px);
    user-select: none;
    pointer-events: none;
}

/*
    Desktop: reserve one fixed gallery area before images load.
    The outer containers own card stability. The image keeps its aspect ratio.
*/
@media (min-width: 701px) {
    .recent-post-card .whusup-post-gallery {
        display: block;
        width: 100%;
        height: 444px;
        min-height: 444px;
        max-height: 444px;
        margin: 10px 0 13px;
        padding: 0;
        overflow: hidden;
        box-sizing: border-box;
        flex: 0 0 auto;
    }

    .recent-post-card .whusup-gallery-stage {
        display: block;
        width: 100%;
        height: 444px;
        min-height: 444px;
        max-height: 444px;
        overflow: hidden;
        box-sizing: border-box;
    }

    .recent-post-card .whusup-gallery-track {
        display: flex;
        width: 100%;
        height: 420px;
        min-height: 420px;
        max-height: 420px;
        box-sizing: border-box;
    }

    .recent-post-card .whusup-gallery-slide {
        position: relative;
        height: 420px;
        min-height: 420px;
        max-height: 420px;
        overflow: hidden;
    }

    .recent-post-card .whusup-gallery-frame {
        width: 100%;
        min-width: 100%;
        max-width: 100%;
        height: 420px;
        min-height: 420px;
        max-height: 420px;
        overflow: hidden;
        border-radius: var(--whusup-image-radius);
    }

    .recent-post-card .whusup-gallery-image,
    .recent-post-card .whusup-gallery-image.blurred-post-image {
        width: 100%;
        height: 100%;
        min-width: 0;
        min-height: 0;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        object-position: center center;
        transform: none;
    }

    .recent-post-card .whusup-gallery-image.blurred-post-image {
        filter: blur(14px);
        transform: none;
    }

    .recent-post-card .whusup-gallery-stage .post-image-carousel-arrow {
        top: 210px;
        z-index: 5;
    }

    .recent-post-card .whusup-gallery-stage .post-image-carousel-dots {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 7px;
        height: 14px;
        min-height: 14px;
        margin: 0;
        z-index: 5;
    }
}

.post-image-carousel-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 32px;
    height: 46px;
    border: none;
    border-radius: 0;
    background: transparent;
    color: rgba(17, 24, 39, 0.72);
    font-size: 42px;
    font-weight: 500;
    line-height: 1;
    cursor: pointer;
    z-index: 3;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease, transform 0.2s ease, opacity 0.2s ease;
    opacity: 0.72;
    text-shadow: 0 1px 3px rgba(255,255,255,0.9);
}

.post-image-carousel-arrow:hover {
    color: rgba(17, 24, 39, 0.96);
    transform: translateY(-50%) scale(1.08);
    opacity: 1;
}

.post-image-carousel-prev {
    left: 4px;
}

.post-image-carousel-next {
    right: 4px;
}

.post-image-carousel-dots {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 7px;
    margin-top: 6px;
}

.post-image-carousel-dot {
    width: 7px;
    height: 7px;
    border: none;
    border-radius: 999px;
    padding: 0;
    background: #d1d5db;
    cursor: pointer;
    transition: background 0.2s ease, transform 0.2s ease, width 0.2s ease;
}

.post-image-carousel-dot.active {
    width: 18px;
    background: #111827;
}

.post-image-carousel-dot:hover {
    background: #6b7280;
}

.recent-post-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    border-top: 1px solid #e5e7eb;
    padding-top: 11px;
    margin-top: 6px;
}

.post-tag {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 80px;
    font-size: 14px;
    color: #5C6670;
    font-weight: 700;
    text-align: center;
    white-space: nowrap;
    flex-shrink: 0;
    text-decoration: none;
}

.post-tag:hover {
    color: #111827;
    text-decoration: underline;
}

.recent-post-actions,
.owner-action-row,
.post-owner-actions {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.owner-action-row,
.post-owner-actions {
    margin-bottom: 15px;
}

.post-delete-form {
    display: flex;
    align-items: center;
    margin: 0;
    padding: 0;
}

.like-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    background: transparent;
    color: #111827;
    font-size: 14px;
    font-weight: 700;
}

.like-indicator.can-like {
    cursor: pointer;
}

.like-indicator.cannot-like {
    cursor: default;
}

.like-icon svg {
    width: 22px;
    height: 22px;
    display: block;
}

.like-icon svg path {
    fill: #ffffff;
    stroke: #111827;
    stroke-width: 2;
    stroke-linejoin: round;
}

.like-indicator.liked .like-icon svg path {
    fill: #111827;
    stroke: #111827;
}

.recent-post-button,
.edit-toggle-button,
.delete-button {
    border: none;
    padding: 9px 18px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.recent-post-button {
    background: #1d4ed8;
    color: white;
}

.edit-toggle-button {
    background: #6b7280;
    color: white;
}

.delete-button {
    background: #dc2626;
    color: white;
}

.small-action-button {
    padding: 7px 15px;
    font-size: 12px;
}

.comment-icon-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #111827;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    user-select: none;
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.comment-icon-toggle:hover {
    opacity: 0.7;
    transform: translateY(-1px);
}

.comment-icon {
    font-size: 20px;
    line-height: 1;
}

.comments-section {
    display: none;
    margin-top: 14px;
    border-top: 1px solid #e5e7eb;
    padding-top: 15px;
}

.comments-section.open {
    display: block;
}

.comment-list {
    margin-top: 10px;
}

.no-comments-text {
    margin-top: 10px;
    color: #777;
    font-size: 14px;
}

.comment-box {
    background: #f8fafc;
    padding: 14px;
    border-radius: 14px;
    margin-top: 12px;
    border: 1px solid #e5e7eb;
    transition: background 0.2s ease;
}

.comment-box:hover {
    background: #f1f5f9;
}

.comment-reply {
    margin-left: 34px;
    margin-top: 10px;
    border-left: 3px solid #dbeafe;
}

.comment-name {
    font-weight: 700;
    color: #111827;
    font-size: 14px;
}

.comment-date {
    color: #9ca3af;
    font-size: 11px;
    margin-top: 2px;
    margin-bottom: 8px;
}

.comment-body {
    color: #374151;
    line-height: 1.55;
    font-size: 14px;
    margin-bottom: 10px;
    word-break: break-word;
}

.comment-owner-actions {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.comment-owner-actions .like-indicator {
    font-size: 12px;
    padding: 0;
}

.comment-owner-actions .like-icon svg {
    width: 18px;
    height: 18px;
}

.comment-like-indicator.can-like:hover {
    transform: translateY(-1px);
}

.comment-action-link {
    background: none;
    border: none;
    padding: 0;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.2s ease;
}

.comment-action-link:hover {
    opacity: 0.7;
}

.comment-edit-link {
    color: #2563eb;
}

.comment-delete-link {
    color: #dc2626;
}

.comment-reply-link {
    color: #4b5563;
}

.reply-collapse-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    background: #ffffff;
    border: 1px solid #dbeafe;
    color: #2563eb;
    padding: 7px 13px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: background 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
}

.reply-collapse-toggle::before {
    content: "↳";
    font-weight: 900;
}

.reply-collapse-toggle:hover {
    background: #eff6ff;
    border-color: #bfdbfe;
    transform: translateY(-1px);
}

.collapsed-replies {
    display: none;
}

.collapsed-replies.open {
    display: block;
}

.recent-post-body a,
.comment-body a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
    word-break: break-word;
}

.comment-form,
.edit-post-form,
.edit-comment-form {
    margin-top: 15px;
}

.comment-form textarea,
.edit-post-form textarea,
.edit-comment-form textarea {
    width: 100%;
    min-height: 75px;
    margin-top: 8px;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid #d1d5db;
    resize: vertical;
    box-sizing: border-box;
    font-family: inherit;
    font-size: 14px;
}

.edit-post-section,
.edit-comment-section,
.reply-form-section {
    display: none;
    margin-top: 12px;
    padding: 12px;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.edit-post-section.open,
.edit-comment-section.open,
.reply-form-section.open {
    display: block;
}

.no-posts,
.login-message {
    text-align: center;
    color: #777;
}

.recent-post-error,
.ajax-comment-message {
    color: #b91c1c;
    background: #fee2e2;
    padding: 8px;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 13px;
}

.ajax-comment-message {
    display: none;
}


.image-lightbox {
    position: fixed;
    inset: 0;
    z-index: 20000;
    background: rgba(17, 24, 39, 0.92);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 28px;
    box-sizing: border-box;
    touch-action: pan-y;
}

.image-lightbox.open {
    display: flex;
}

.image-lightbox-content {
    position: relative;
    max-width: 96vw;
    max-height: 90vh;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--whusup-image-radius);
    overflow: hidden;
    line-height: 0;
    -webkit-mask-image: -webkit-radial-gradient(white, black);
}

.image-lightbox-image {
    display: block;
    width: auto;
    height: auto;
    max-width: 96vw;
    max-height: 90vh;
    object-fit: contain;
    border-radius: 0;
    box-shadow: 0 18px 60px rgba(0,0,0,0.42);
    background: transparent;
}

.image-lightbox-close {
    position: fixed;
    top: 18px;
    right: 22px;
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 999px;
    background: rgba(255,255,255,0.14);
    color: #ffffff;
    font-size: 32px;
    line-height: 1;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease, transform 0.2s ease;
    z-index: 20003;
}

.image-lightbox-close:hover {
    background: rgba(255,255,255,0.24);
    transform: scale(1.04);
}

.image-lightbox-arrow {
    position: fixed;
    top: 50%;
    transform: translateY(-50%);
    width: 44px;
    height: 58px;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.86);
    font-size: 56px;
    font-weight: 300;
    line-height: 1;
    cursor: pointer;
    z-index: 20002;
    display: none;
    align-items: center;
    justify-content: center;
    text-shadow: 0 2px 8px rgba(0,0,0,0.35);
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.image-lightbox-arrow:hover {
    opacity: 1;
    transform: translateY(-50%) scale(1.06);
}

.image-lightbox-prev {
    left: 20px;
}

.image-lightbox-next {
    right: 20px;
}

.image-lightbox.has-multiple .image-lightbox-arrow {
    display: inline-flex;
}

.image-lightbox-dots {
    position: fixed;
    left: 50%;
    bottom: 22px;
    transform: translateX(-50%);
    display: none;
    align-items: center;
    justify-content: center;
    gap: 8px;
    z-index: 20002;
}

.image-lightbox.has-multiple .image-lightbox-dots {
    display: flex;
}

.image-lightbox-dot {
    width: 8px;
    height: 8px;
    border: none;
    border-radius: 999px;
    padding: 0;
    background: rgba(255,255,255,0.42);
    cursor: pointer;
    transition: background 0.2s ease, width 0.2s ease;
}

.image-lightbox-dot.active {
    width: 20px;
    background: #ffffff;
}

body.lightbox-open {
    overflow: hidden;
}



@media (max-width: 700px) {
    .recent-posts-wrapper {
        max-width: none;
        width: 100%;
        margin: 0 auto 22px;
        padding: 16px 10px 0;
    }

    .recent-post-card {
        border-radius: 14px;
        padding: 18px;
        margin-bottom: 18px;
    }

    .profile-emblem {
        top: 14px;
        right: 14px;
        width: 48px;
        height: 48px;
        font-size: 15px;
    }

    .sort-button {
        flex: 1;
        text-align: center;
    }

    .recent-post-footer {
        align-items: center;
    }

    .comment-reply {
        margin-left: 18px;
    }


    .whusup-gallery-image {
        max-height: 340px;
    }

    .whusup-gallery-slide {
        max-height: 340px;
    }

    .whusup-gallery-frame {
        max-height: 340px;
    }

    /*
        Mobile portrait-photo upgrade:
        JavaScript adds these classes only when the uploaded image is portrait-oriented.
        Portrait photos fill the mobile card width, keep their aspect ratio, and are clipped
        only if they would make the post card excessively tall. The lightbox still shows
        the full uncropped original.
    */
    .whusup-gallery-slide.mobile-portrait-slide {
        max-height: none;
        align-items: stretch;
    }

    .whusup-gallery-frame.mobile-portrait-frame {
        display: block;
        width: 100%;
        max-width: 100%;
        max-height: min(560px, 78vh);
        overflow: hidden;
        border-radius: var(--whusup-image-radius);
        background: transparent;
    }

    .whusup-gallery-image.mobile-portrait-image {
        display: block;
        width: 100%;
        height: auto;
        max-width: 100%;
        max-height: none;
        object-fit: contain;
        object-position: center;
        border-radius: inherit;
    }

    .post-image-carousel-arrow {
        width: 30px;
        height: 42px;
        font-size: 38px;
    }

    .post-image-carousel-prev {
        left: 2px;
    }

    .post-image-carousel-next {
        right: 2px;
    }

    .image-lightbox {
        padding: 16px;
    }

    .image-lightbox-image {
        max-width: 96vw;
        max-height: 86vh;
    }

    .image-lightbox-content {
        max-width: 96vw;
        max-height: 86vh;
    }

    .image-lightbox-arrow {
        width: 36px;
        height: 50px;
        font-size: 46px;
    }

    .image-lightbox-prev {
        left: 8px;
    }

    .image-lightbox-next {
        right: 8px;
    }

    .image-lightbox-close {
        top: 12px;
        right: 12px;
    }
}


.post-permalink {
    color: inherit;
    text-decoration: none;
}

.post-permalink:hover {
    text-decoration: underline;
}




.whusup-page-title {
    text-align: center;
    margin: 0 0 16px;
    font-size: 1.55rem;
    font-weight: 800;
    color: #111827;
}

.whusup-search-form {
    max-width: 600px;
    margin: 0 auto 20px;
}

.whusup-search-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 999px;
    outline: none;
    font-size: 1rem;
}

.whusup-search-input:focus {
    border-color: #9ca3af;
    box-shadow: 0 0 0 4px rgba(156, 163, 175, 0.18);
}

</style>
