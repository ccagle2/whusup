<?php

if (!function_exists('renderCommentTree')) {
function renderCommentTree($comments, $parent_id, $post_id, $user_id, $is_logged_in) {
    foreach ($comments as $comment) {
        $comment_parent = $comment['parent_comment_id'] ?? null;

        if ((string) $comment_parent !== (string) $parent_id) {
            continue;
        }

        $comment_id = (int) $comment['id'];
        $is_comment_owner = $is_logged_in && $comment['user_id'] === $user_id;
        $is_reply = !empty($comment['parent_comment_id']);
        $child_count = getDirectChildCount($comments, $comment_id);
        $comment_like_count = (int) ($comment['like_count'] ?? 0);
        $comment_user_liked = !empty($comment['user_liked']);
        ?>

        <div 
            class="comment-box <?= $is_reply ? 'comment-reply' : '' ?>" 
            data-comment-id="<?= htmlspecialchars($comment_id) ?>"
            data-parent-comment-id="<?= htmlspecialchars($comment['parent_comment_id'] ?? '') ?>"
        >
            <div class="comment-name <?= !$is_logged_in ? 'blurred-name' : '' ?>">
                <?= htmlspecialchars(ucwords($comment['name'])) ?>
            </div>

            <div class="comment-date">
                <?= htmlspecialchars(timeAgo($comment['created_at'])) ?>
            </div>

            <div class="comment-body">
                <?= linkifyText($comment['comment_body']) ?>
            </div>

            <div class="comment-owner-actions">
                <span
                    class="like-indicator comment-like-indicator ajax-comment-like-indicator <?= $comment_user_liked ? 'liked' : '' ?> <?= $is_logged_in ? 'can-like' : 'cannot-like' ?>"
                    data-comment-id="<?= htmlspecialchars($comment_id) ?>"
                    data-logged-in="<?= $is_logged_in ? '1' : '0' ?>"
                    title="<?= $is_logged_in ? 'Like comment' : 'Log in to like comments' ?>"
                >
                    <span class="like-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 10v10H4V10h3zm3 10h7.4c.9 0 1.7-.6 1.9-1.5l1.4-6.2c.3-1.2-.6-2.3-1.9-2.3h-5.1l.8-3.8c.1-.6-.1-1.2-.5-1.6L13.2 4 9 9.1V20z"/>
                        </svg>
                    </span>
                    <span class="like-count comment-like-count"><?= htmlspecialchars($comment_like_count) ?></span>
                </span>

                <?php if ($is_logged_in): ?>
                    <button
                        type="button"
                        class="comment-action-link comment-reply-link reply-toggle-button"
                        data-target="reply-form-<?= htmlspecialchars($comment_id) ?>"
                    >
                        Reply
                    </button>
                <?php endif; ?>

                <?php if ($is_comment_owner): ?>
                    <button 
                        type="button" 
                        class="comment-action-link comment-edit-link edit-comment-toggle-button"
                        data-target="edit-comment-<?= htmlspecialchars($comment_id) ?>"
                    >
                        Edit
                    </button>

                    <button 
                        type="button" 
                        class="comment-action-link comment-delete-link ajax-delete-comment-button"
                        data-comment-id="<?= htmlspecialchars($comment_id) ?>"
                    >
                        Delete
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($is_comment_owner): ?>
                <div id="edit-comment-<?= htmlspecialchars($comment_id) ?>" class="edit-comment-section">
                    <form class="edit-comment-form ajax-edit-comment-form">
                        <input type="hidden" name="comment_id" value="<?= htmlspecialchars($comment_id) ?>">
                        <textarea name="comment_body"><?= htmlspecialchars($comment['comment_body']) ?></textarea>

                        <button type="submit" class="recent-post-button small-action-button">
                            Save
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($is_logged_in): ?>
                <div id="reply-form-<?= htmlspecialchars($comment_id) ?>" class="reply-form-section">
                    <form class="comment-form ajax-add-comment-form">
                        <input type="hidden" name="post_id" value="<?= htmlspecialchars($post_id) ?>">
                        <input type="hidden" name="parent_comment_id" value="<?= htmlspecialchars($comment_id) ?>">

                        <textarea name="comment_body" placeholder="Write a reply..."></textarea>

                        <button type="submit" class="recent-post-button small-action-button">
                            Reply
                        </button>

                        <div class="ajax-comment-message"></div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($child_count > 0): ?>
                <button
                    type="button"
                    class="reply-collapse-toggle"
                    data-target="comment-children-<?= htmlspecialchars($comment_id) ?>"
                    data-count="<?= htmlspecialchars($child_count) ?>"
                >
                    View <?= htmlspecialchars($child_count) ?> repl<?= $child_count === 1 ? 'y' : 'ies' ?>
                </button>

                <div id="comment-children-<?= htmlspecialchars($comment_id) ?>" class="comment-children collapsed-replies">
                    <?php renderCommentTree($comments, $comment_id, $post_id, $user_id, $is_logged_in); ?>
                </div>
            <?php else: ?>
                <div id="comment-children-<?= htmlspecialchars($comment_id) ?>" class="comment-children"></div>
            <?php endif; ?>
        </div>

        <?php
    }
}
}
