<?php

if (!function_exists('renderPostCards')) {
function renderPostCards($recent_posts, $comments_by_post, $post_images_by_post, $user_id = null, $is_logged_in = false, $redirect_url = '') {
    ob_start();

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
                $is_owner = $is_logged_in && $post['user_id'] === $user_id;

                $name_parts = explode(' ', trim($post['name']));
                $first_initial = strtoupper(substr($name_parts[0], 0, 1));
                $last_initial = '';

                if (count($name_parts) > 1) {
                    $last_initial = strtoupper(substr(end($name_parts), 0, 1));
                }

                $first_letter = $first_initial . $last_initial;

                $profileImageUrl = !empty($post['profile_picture_url'])
                    ? getS3ImageUrl($post['profile_picture_url'])
                    : null;
            ?>

            <div class="recent-post-card" data-post-id="<?= htmlspecialchars($post_id) ?>">

                <div class="profile-emblem <?= !$is_logged_in ? 'blurred-name' : '' ?>">
                    <?php if (!empty($profileImageUrl)): ?>
                        <img
                            src="<?= htmlspecialchars($profileImageUrl) ?>"
                            alt="Profile picture"
                            class="profile-emblem-image <?= $is_logged_in ? 'profile-expandable' : '' ?>"
                            data-full-image="<?= $is_logged_in ? htmlspecialchars($profileImageUrl) : '' ?>"
                            loading="eager"
                        >
                    <?php else: ?>
                        <?= htmlspecialchars($first_letter) ?>
                    <?php endif; ?>
                </div>

                <div class="recent-post-author <?= !$is_logged_in ? 'blurred-name' : '' ?>">
                    <?= htmlspecialchars(ucwords($post['name'])) ?>
                </div>

                <div class="recent-post-date">
                    <a
                        href="/post/<?= urlencode($post_id) ?>/<?= rawurlencode(makePostSlug(!empty($post['tag']) ? $post['tag'] : $post['body'])) ?>?redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '/') ?>"
                        class="post-permalink"
                        title="View this post"
                    >
                        <?= htmlspecialchars(timeAgo($post['created_at'])) ?>
                    </a>
                </div>

                <div class="recent-post-body">
                    <?= linkifyText($post['body']) ?>
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
                                                    class="whusup-gallery-image <?= !$is_logged_in ? 'blurred-post-image' : '' ?>"
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
                            action="<?= htmlspecialchars($redirect_url) ?>" 
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

                    </div>

                    <?php if (!empty($post['tag'])): ?>
                        <a
                            href="/tag/<?= rawurlencode(makePostSlug($post['tag'])) ?>"
                            class="post-tag"
                            title="View posts tagged <?= htmlspecialchars($post['tag']) ?>"
                        >
                            <?= htmlspecialchars($post['tag']) ?>
                        </a>
                    <?php endif; ?>
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
